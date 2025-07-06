<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // Insert datos for admin
        $adminDatosId = DB::table('datos')->insertGetId([
            'nombre' => 'Admin',
            'apellido' => 'User',
            'email' => 'admin@example.com',
            'direccion' => 'Av. Siempre Viva 123',
            'dni' => '12345678',
            'ruc' => '20123456789',
            'telefono' => '987654321',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert datos for cliente
        $clienteDatosId = DB::table('datos')->insertGetId([
            'nombre' => 'Cliente',
            'apellido' => 'Ejemplo',
            'email' => 'cliente@example.com',
            'direccion' => 'Calle Falsa 456',
            'dni' => '87654321',
            'ruc' => '20987654321',
            'telefono' => '912345678',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get role IDs
        $adminRolId = DB::table('roles')->where('nombre', 'admin')->value('idRol');
        $clienteRolId = DB::table('roles')->where('nombre', 'cliente')->value('idRol');
        $avalRolId = DB::table('roles')->where('nombre', 'aval')->value('idRol');

        // Insert usuarios
        DB::table('usuarios')->insert([
            [
                'username' => 'admin123',
                'password' => Hash::make('12345678'),
                'idDatos' => $adminDatosId,
                'idRol' => $adminRolId,
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => null,
                'password' => null,
                'idDatos' => $clienteDatosId,
                'idRol' => $clienteRolId,
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

       // Generate 1000 random clients with random active/inactive status
        for ($i = 0; $i < 5; $i++) {
            $clienteDatosId = DB::table('datos')->insertGetId([
                'nombre' => $faker->firstName,
                'apellido' => $faker->lastName,
                'email' => $faker->unique()->safeEmail,
                'direccion' => $faker->streetAddress,
                'dni' => $faker->numerify('########'),
                'ruc' => '20' . $faker->numerify('#########'),
                'telefono' => $faker->numerify('9########'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Randomly set status as 'activo' or 'inactivo' 
            $estado = $faker->randomElement([0,1]);
            
            DB::table('usuarios')->insert([
                'username' => null,
                'password' => null,
                'idDatos' => $clienteDatosId,
                'idRol' => $clienteRolId,
                'estado' => $estado,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        for ($i = 0; $i < 5; $i++) { // You can adjust the number as needed
            $avalDatosId = DB::table('datos')->insertGetId([
                'nombre' => $faker->firstName,
                'apellido' => $faker->lastName,
                'email' => $faker->unique()->safeEmail,
                'direccion' => $faker->streetAddress,
                'dni' => $faker->numerify('########'),
                'ruc' => '20' . $faker->numerify('#########'),
                'telefono' => $faker->numerify('9########'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Similar active/inactive distribution as clients
            $estado = $faker->randomElement([0,1]);
            
            DB::table('usuarios')->insert([
                'username' => null,
                'password' => null,
                'idDatos' => $avalDatosId,
                'idRol' => $avalRolId,
                'estado' => $estado,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}