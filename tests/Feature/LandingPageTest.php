<?php

namespace Tests\Feature;

use App\Enums\FrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_can_be_rendered(): void
    {
        $response = $this->get(route(FrontRoutes::Home));

        $response->assertOk();
    }

    public function test_parse_route_requires_pdf_file(): void
    {
        $response = $this->postJson(route(FrontRoutes::Parse), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('pdf');
    }

    public function test_parse_route_rejects_non_pdf_files(): void
    {
        $file = UploadedFile::fake()->image('not-a-pdf.png');

        $response = $this->postJson(route(FrontRoutes::Parse), ['pdf' => $file]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('pdf');
    }
}
