<?php
// app/Http/Resources/TradingJournalResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TradingJournalCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($journal) {
                return new TradingJournalResource($journal);
            }),
            'meta' => [
                'total' => $this->total(),
                'count' => $this->count(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'total_pages' => $this->lastPage(),
                'links' => [
                    'first' => $this->url(1),
                    'last' => $this->url($this->lastPage()),
                    'prev' => $this->previousPageUrl(),
                    'next' => $this->nextPageUrl()
                ]
            ],
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'emiten' => $request->emiten,
                'type' => $request->type,
                'status' => $request->status,
                'strategy' => $request->strategy
            ]
        ];
    }
}
