<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Data demo deterministik. Password demo bersifat lokal (bukan secret produksi).
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Akun demo per konteks (Modul 2). Role diformalkan pada Modul 4–5.
        $demo = [
            ['name' => 'Admin Kantin', 'email' => 'admin@kantin.test', 'role' => 'admin'],
            ['name' => 'Operator Tenant', 'email' => 'tenant@kantin.test', 'role' => 'tenant'],
        ];

        foreach ($demo as $row) {
            // Penetapan atribut langsung: role/status sengaja tidak mass-assignable.
            $user = User::firstOrNew(['email' => $row['email']]);
            $user->name = $row['name'];
            $user->role = $row['role'];
            $user->status = 'active';
            $user->password = Hash::make('password');
            $user->email_verified_at = now();
            $user->save();
        }
    }
}
