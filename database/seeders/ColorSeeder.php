<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    /**
     * Color para productos que no se diferencian por color.
     */
    public function run(): void
    {
        Color::firstOrCreate(['nombre' => 'Único'], ['activo' => true]);
    }
}
