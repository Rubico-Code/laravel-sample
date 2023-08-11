<?php

declare(strict_types=1);

namespace App\Jobs\Imports;

use App\Models\Offer;
use App\Models\Store;
use Illuminate\Validation\Rule;

class OfferSheetImportJob extends AbstractFileImportJob
{
    protected function transformRow(int $index, array $inputs): array
    {
        $this->transformDateCell($inputs, 'expires_at');
        $this->transformCommaSeperatedIds($inputs, 'store_hubspot_ids');

        return $inputs;
    }

    protected function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'discount_policy' => ['required', 'string', 'min:2', 'max:255'],
            'expires_at' => ['required', 'date_format:"Y-m-d H:i:s"'],
            'exclusions' => ['nullable', 'string', 'min:3', 'max:500'],
            'is_single_use' => ['required', 'boolean'],
            'max_redemption_count' => [
                'required_if:is_single_use,0',
                'integer',
                'min:1',
                'max:999',
            ],
            'max_redemption_per_day' => [
                'required_if:is_single_use,0',
                'integer',
                'min:1',
                'max:99',
            ],
            'store_hubspot_ids' => ['bail', 'nullable', 'array', 'max:99'],
            'store_hubspot_ids.*' => [
                'bail',
                'required',
                'max:255',
                'distinct',
                Rule::exists(Store::class, 'hubspot_id'),
            ],
        ];
    }

    protected function processRow()
    {
        $inputs = collect($this->currentRow);
        $offer = new Offer();

        if ($id = $inputs->get('id')) {
            $offer = Offer::findOr($id, function () use ($id) {
                throw new \InvalidArgumentException('No offer exists with id: '.$id);
            });
        }

        $offer->fill(
            $inputs->only(array_keys($this->rules()))
                ->except(['id', 'store_hubspot_ids'])
                ->toArray()
        );
        $offer->save();

        $this->saveStores($offer);

        return $offer;
    }

    protected function saveStores(Offer $offer)
    {
        $stores = collect($this->findStores());
        $offer->stores()->sync($stores);
    }

    protected function findStores(): array
    {
        $ids = $this->currentRow['store_hubspot_ids'] ?? [];

        if (empty($ids)) {
            return [];
        }

        return Store::query()
            ->whereIn('hubspot_id', $ids)
            ->get(['id', 'hubspot_id'])
            ->modelKeys();
    }
}
