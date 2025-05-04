<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Region;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Region::factory(10)->create()->each(function (Region $region) {
        //     $region->children()->saveMany(Region::factory(random_int(3, 10))->create()->each(function (Region $region) {
        //         $region->children()->saveMany(Region::factory(random_int(3, 10))->make());
        //     }));
        // });

        // https://stackoverflow.com/questions/49047683/laravel-how-to-convert-stdclass-object-to-array
        $regions1 = DB::table('regions1')->get();
        $regions1 = $regions1->map(function ($i) {
            $i->created_at = Carbon::now();

            return (array) $i;
        })->toArray();
        DB::table('regions')->insert($regions1);

        $regions = Region::all();
        foreach ($regions as $region) {
            $region->slug = Str::of($region->name)->slug('-');
            $region->save();
        }
    }
}
