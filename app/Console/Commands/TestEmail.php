<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

final class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'email:test {email} {--subject=Test Email} {--message=This is a test email from your Laravel application}';

    /**
     * The console command description.
     */
    protected $description = 'Send a test email to verify SMTP configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $subject = $this->option('subject');
        $messageContent = $this->option('message');

        $this->info("Sending test email to: {$email}");
        $this->info("Subject: {$subject}");

        try {
            Mail::raw($messageContent, function (Message $message) use ($email, $subject): void {
                $message->to($email)
                    ->subject($subject);
            });

            $this->info('✅ Email sent successfully!');
            $this->info('Check the recipient\'s inbox (and spam folder) for the test email.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to send email:');
            $this->error($e->getMessage());

            $this->newLine();
            $this->warn('Troubleshooting tips:');
            $this->line('1. Check your .env file for correct SMTP settings');
            $this->line('2. Verify your email credentials');
            $this->line('3. Check storage/logs/laravel.log for detailed error messages');
            $this->line('4. If using Gmail, ensure you\'re using an App Password');

            return Command::FAILURE;
        }
    }
}
