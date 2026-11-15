<?php

namespace DigitalLabs\Core\Repositories;

use DigitalLabs\Core\Eloquent\Repository;
use DigitalLabs\Core\StoredFiles;

class ChannelRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'DigitalLabs\Core\Contracts\Channel';
    }

    /**
     * Create.
     *
     * @return \DigitalLabs\Core\Contracts\Channel
     */
    public function create(array $data)
    {

        $model = $this->getModel();

        foreach (core()->getAllLocales() as $locale) {
            foreach ($model->translatedAttributes as $attribute) {
                if (isset($data[$attribute])) {
                    $data[$locale->code][$attribute] = $data[$attribute];
                }
            }
        }

        $channel = parent::create($data);

        $channel->locales()->sync($data['locales']);

        $channel->currencies()->sync($data['currencies']);

        $channel->inventory_sources()->sync($data['inventory_sources']);

        $this->uploadImages($data, $channel);

        $this->uploadImages($data, $channel, 'favicon');

        return $channel;
    }

    /**
     * Update.
     *
     * @param  int  $id
     * @return \DigitalLabs\Core\Contracts\Channel
     */
    public function update(array $data, $id)
    {
        $channel = parent::update($data, $id);

        $channel->locales()->sync($data['locales']);

        $channel->currencies()->sync($data['currencies']);

        $channel->inventory_sources()->sync($data['inventory_sources']);

        $this->uploadImages($data, $channel);

        $this->uploadImages($data, $channel, 'favicon');

        return $channel;
    }

    /**
     * Upload images.
     *
     * @param  array  $data
     * @param  \DigitalLabs\Core\Contracts\Channel  $channel
     * @param  string  $type
     * @return void
     */
    public function uploadImages($data, $channel, $type = 'logo')
    {
        if (isset($data[$type])) {
            foreach ($data[$type] as $imageId => $image) {
                $file = $type.'.'.$imageId;

                if (request()->hasFile($file)) {
                    $replacedFile = $channel->{$type};

                    $channel->{$type} = request()->file($file)->store('channel/'.$channel->id);

                    $channel->save();

                    StoredFiles::deleteIfUnused($replacedFile);
                }
            }
        } else {
            $replacedFile = $channel->{$type};

            $channel->{$type} = null;

            $channel->save();

            StoredFiles::deleteIfUnused($replacedFile);
        }
    }
}
