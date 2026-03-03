<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentTemplate;

class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DocumentTemplate::firstOrCreate(
            ['file_name' => 'consent-homecare-service.pdf'],
            [
                'name' => 'Consent for Homecare Service & Client Agreement',
                'description' => '2-page consent form for homecare service and client agreement.',
                'is_active' => true,
            ]
        );
    }
}
