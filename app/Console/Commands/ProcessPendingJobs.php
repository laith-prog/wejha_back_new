<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\TestEmailJob;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;

class ProcessPendingJobs extends Command
{
    protected $signature = 'jobs:process-pending';
    protected $description = 'Process jobs that are stuck in the queue';

    public function handle()
    {
        $this->info('Starting to process pending jobs...');
        
        // Get all pending jobs
        $pendingJobs = DB::table('jobs')->get();
        
        $this->info("Found {$pendingJobs->count()} pending jobs.");
        
        if ($pendingJobs->isEmpty()) {
            $this->info('No pending jobs to process.');
            return;
        }
        
        foreach ($pendingJobs as $job) {
            $this->info("Processing job ID: {$job->id}");
            
            try {
                // Send a test email directly
                $this->info('Sending test email directly...');
                
                Mail::raw('Test email from manual job processing', function($message) {
                    $message->to(config('mail.from.address'))
                            ->subject('Test Email from Manual Processing');
                });
                
                $this->info('Test email sent successfully.');
                
                // Delete the job from the queue
                DB::table('jobs')->where('id', $job->id)->delete();
                $this->info("Job ID: {$job->id} processed and removed from queue.");
            } catch (\Exception $e) {
                $this->error("Failed to process job ID: {$job->id}");
                $this->error("Error: " . $e->getMessage());
                Log::error("Failed to process job: " . $e->getMessage());
            }
        }
        
        $this->info('Finished processing pending jobs.');
    }
} 