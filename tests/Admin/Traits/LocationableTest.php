<?php

namespace Tests\Admin\Traits;


use Igniter\Admin\Models\Category;
use Igniter\Admin\Models\Location;

it('can assign a locationable model to a location', function () {
    $location = Location::factory()->create();
    $locationable = Category::factory()->create();

    $location_categories_query = Category::whereHasLocation($location->getKey());

    $this->assertEmpty($locationable->locations);
    $this->assertEmpty($location_categories_query->get());

    $locationable->locations = [$location->getKey()];

    $locationable->save();
    $locationable->fresh();

    $this->assertContains($location->getKey(), $locationable->locations->pluck($location->getKeyName()));
    $this->assertContains($locationable->getKey(), $location_categories_query->get()->pluck($locationable->getKeyName()));
});
