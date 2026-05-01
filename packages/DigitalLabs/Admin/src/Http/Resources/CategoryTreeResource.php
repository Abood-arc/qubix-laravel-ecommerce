<?php

namespace DigitalLabs\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryTreeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => $this->url,
            'status' => $this->status,
            // Plain nested arrays — admin v-tree-view expects iterable child nodes, not JsonResource { "data": [...] } wrappers.
            'children' => $this->children
                ->map(fn ($child) => (new self($child))->toArray($request))
                ->values()
                ->all(),
        ];
    }
}
