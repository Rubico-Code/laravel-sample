<?php

declare(strict_types=1);

namespace App\Jobs\Imports;

use App\Enums\Timezone;
use App\Jobs\Imports\Concerns\TrackExceptions;
use App\Jobs\Imports\Concerns\TracksValidationErrors;
use App\Mail\Admin\FileImportFinishedMail;
use App\Models\FileImport;
use App\Support\ArrayNormaliser;
use App\Support\TempFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator as ValidationFacade;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;
use Throwable;

abstract class AbstractFileImportJob implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue, Queueable, SerializesModels;
    use TrackExceptions, TracksValidationErrors;

    public int $maxExceptions = 3;

    public bool $failOnTimeout = true;

    public bool $deleteWhenMissingModels = true;

    public ?TempFile $tempFile = null;

    public ?int $currentIndex = null;

    public ?array $currentRow = null;

    public function __construct(public FileImport $fileImport)
    {
        //
    }

    public function uniqueId()
    {
        return $this->fileImport->type;
    }

    public function handle()
    {
        Log::info('Starting import for file: '.$this->fileImport->getKey());

        $rows = $this->getRows();

        $rows->each(function (array $row, $index) {
            $this->currentIndex = $index;
            $this->currentRow = $this->transformRow($index, $row);
            $this->currentRow = ArrayNormaliser::normalise($this->currentRow);

            if ($this->validateRow()) {
                try {
                    DB::beginTransaction();
                    $this->processRow();
                    DB::commit();
                } catch (Throwable $exception) {
                    DB::rollBack();
                    $this->onException($exception);

                    if (app()->hasDebugModeEnabled()) {
                        Log::error($exception, [
                            'index' => $this->currentIndex,
                            'row' => $this->currentRow,
                        ]);
                    }
                }
            }
        });

        $this->markFinished();
        $this->cleanup();
        $this->notifyUser();

        Log::info('Finished import for file: '.$this->fileImport->getKey());
    }

    protected function getRows(): LazyCollection
    {
        $stream = Storage::readStream($this->fileImport->file);
        $extension = Str::of($this->fileImport->file)->afterLast('.')->prepend('.')->value();

        $this->tempFile = TempFile::fresh()->withSuffix($extension)->write($stream);

        return SimpleExcelReader::create($this->tempFile->getPath())
            ->trimHeaderRow()
            ->headersToSnakeCase()
            ->take(500) // hard limit according to job timeout
            ->getRows();
    }

    abstract protected function rules(): array;

    protected function validateRow(): bool
    {
        $validator = ValidationFacade::make($this->currentRow, $this->rules());
        $this->withValidator($validator);

        if ($validator->fails()) {
            $this->onValidationError($validator->errors()->getMessages());

            return false;
        }

        return true;
    }

    protected function withValidator(Validator $validator)
    {
        //
    }

    protected function transformRow(int $index, array $inputs): array
    {
        return $inputs;
    }

    protected function transformDateCell(array &$inputs, string $key)
    {
        if (! empty($inputs[$key]) && $inputs[$key] instanceof \DateTimeInterface) {
            $date = $inputs[$key]->format('Y-m-d H:i:s');

            $inputs[$key] = Carbon::parse($date, Timezone::client())
                ->tz(config('app.timezone'))
                ->format('Y-m-d H:i:s');
        }
    }

    protected function transformCommaSeperatedIds(array &$inputs, string $key)
    {
        if (! empty($inputs[$key])) {
            $inputs[$key] = array_filter(explode(',', (string) $inputs[$key]));
        }
    }

    abstract protected function processRow();

    public function failed(?Throwable $exception)
    {
        $this->onException($exception);
        $this->markFinished();
        $this->cleanup();
        $this->notifyUser();
    }

    protected function cleanup(): void
    {
        $this->tempFile?->delete();
        $this->tempFile = null;
    }

    protected function markFinished(): void
    {
        DB::beginTransaction();

        // Prevent other jobs from reading and writing to this row unless this transaction is committed
        $fileImport = FileImport::lockForUpdate()->findOrFail($this->fileImport->getKey());
        $meta = $fileImport->meta ?? [];
        $meta['exceptions'] = array_merge($meta['exceptions'] ?? [], $this->exceptions());
        $meta['validationErrors'] = array_merge($meta['validationErrors'] ?? [], $this->validationErrors());

        $fileImport->update([
            'finished_at' => Date::now(),
            'meta' => $meta,
        ]);

        DB::commit();
    }

    protected function notifyUser(): void
    {
        Mail::to($this->fileImport->admin)->send(new FileImportFinishedMail($this->fileImport));
    }
}
