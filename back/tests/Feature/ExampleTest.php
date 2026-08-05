<?php

namespace Tests\Feature;

use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_homepage_uses_the_website_controller(): void
    {
        $route = Route::getRoutes()->getByName('website.index');

        $this->assertNotNull($route);
        $this->assertSame(WebsiteController::class.'@index', $route->getActionName());
    }
}
