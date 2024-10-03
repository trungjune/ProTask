<?php

namespace App\Http\Controllers;

use App\Helpers\DatabaseManager;
use Illuminate\Routing\Controller;

class DatabaseController extends Controller
{
    /**
     * @var DatabaseManager
     */
    private $databaseManager;

    /**
     * @param DatabaseManager $databaseManager
     */
    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    /**
     * Migrate and seed the database.
     *
     * @return \Illuminate\View\View
     */
    public function database()
    {
        $response = $this->databaseManager->migrateAndSeed();

        return redirect()->route('LaravelInstaller::admin_setup')->with(['message' => $response]);
//        return redirect()->route('LaravelInstaller::final')->with(['message' => $response]);
    }
}
