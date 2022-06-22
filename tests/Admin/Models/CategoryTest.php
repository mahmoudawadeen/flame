<?php

namespace Tests\Admin\Models;


use Igniter\Admin\Models\Category;
use Illuminate\Database\QueryException;

it('can create a category', function () {
    $this->assertNotNull(Category::factory()->create());
});

it('should fail to create a category when no name is provided', function () {
    $this->expectException(QueryException::class);
    $this->expectExceptionCode(23000);
    $column_name = 'name';
    $this->expectExceptionMessageMatches("/'$column_name' cannot be null/i");
    Category::factory()->create(
        [
            $column_name => null
        ]
    );
});
