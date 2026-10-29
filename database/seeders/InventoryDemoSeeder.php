<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Position;
use App\Models\Section;
use Illuminate\Database\Seeder;

class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $studio = Section::firstOrCreate(['name' => 'Studio']);
        $cucina = Section::firstOrCreate(['name' => 'Cucina']);
        $ripostiglio = Section::firstOrCreate(['name' => 'Ripostiglio']);
        $elettronica = Category::firstOrCreate(['name' => 'Elettronica']);
        $utensili = Category::firstOrCreate(['name' => 'Utensili']);
        $alimenti = Category::firstOrCreate(['name' => 'Dispensa']);
        foreach ([
            [
                'quantity' => 1,
                'name' => 'Cuffie wireless',
                'section_id' => $studio->id,
                'category_id' => $elettronica->id,
                'position' => 'Cassetto A',
                'serial' => '00-WH-1042',
                'purchased_on' => '2025-03-12',
            ],
            [
                'quantity' => 1,
                'name' => 'Bilancia da cucina',
                'section_id' => $cucina->id,
                'category_id' => $elettronica->id,
                'position' => 'Pensile destro',
                'serial' => '000832',
            ],
            [
                'quantity' => 1,
                'name' => 'Trapano a batteria',
                'section_id' => $ripostiglio->id,
                'category_id' => $utensili->id,
                'position' => 'Scaffale 2',
                'serial' => 'DR-0057',
            ],
            [
                'quantity' => 3,
                'name' => 'Caffè in grani',
                'section_id' => $cucina->id,
                'category_id' => $alimenti->id,
                'position' => 'Dispensa',
                'expires_on' => '2027-06-30',
            ],
        ] as $data) {
            InventoryItem::firstOrCreate(['name' => $data['name'], 'section_id' => $data['section_id']], $data);
            Position::firstOrCreate(['section_id' => $data['section_id'], 'name' => $data['position']]);
        }
    }
}
