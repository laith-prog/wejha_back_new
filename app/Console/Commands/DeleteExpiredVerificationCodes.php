<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteExpiredVerificationCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verification:delete-expired-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired verification codes from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deletedCount = DB::table('verification_codes')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted {$deletedCount} expired verification codes.");
        
        return Command::SUCCESS;
    }
} 