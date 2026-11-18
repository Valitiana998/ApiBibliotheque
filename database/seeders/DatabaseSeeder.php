<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Borrow;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'bibliothecaire@example.com'],
            ['name' => 'Bibliothécaire', 'password' => Hash::make('password123')]
        );

        $authors = [
            Author::create(['first_name' => 'Robert', 'last_name' => 'Martin', 'nationality' => 'Américaine']),
            Author::create(['first_name' => 'Victor', 'last_name' => 'Hugo', 'nationality' => 'Française']),
            Author::create(['first_name' => 'Antoine', 'last_name' => 'de Saint-Exupéry', 'nationality' => 'Française']),
        ];

        $books = [
            Book::create(['title' => 'Clean Code', 'isbn' => '978-0132350884', 'year' => 2008, 'available' => true, 'author_id' => $authors[0]->id]),
            Book::create(['title' => 'Clean Architecture', 'isbn' => '978-0134494166', 'year' => 2017, 'available' => true, 'author_id' => $authors[0]->id]),
            Book::create(['title' => 'Les Misérables', 'isbn' => '978-2070408505', 'year' => 1862, 'available' => true, 'author_id' => $authors[1]->id]),
            Book::create(['title' => 'Notre-Dame de Paris', 'isbn' => '978-2070416258', 'year' => 1831, 'available' => false, 'author_id' => $authors[1]->id]),
            Book::create(['title' => 'Le Petit Prince', 'isbn' => '978-2070612758', 'year' => 1943, 'available' => true, 'author_id' => $authors[2]->id]),
        ];

        Borrow::create(['user_name' => 'Rakoto Jean', 'book_id' => $books[3]->id, 'borrowed_at' => now()->subDays(3), 'returned_at' => null]);
        Borrow::create(['user_name' => 'Rasoa Lina', 'book_id' => $books[4]->id, 'borrowed_at' => now()->subDays(7), 'returned_at' => now()->subDay()]);
    }
}
