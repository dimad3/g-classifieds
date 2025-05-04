<?php

declare(strict_types=1);

namespace App\Console\Commands\User;

use App\Models\User\User;
use App\Services\Auth\RegisterService;
use Illuminate\Console\Command;

class VerifyEmailCommand extends Command
{
    protected $signature = 'user:verify-email {email}';

    protected $description = 'Verify user email';

    private $registerService;

    public function __construct(RegisterService $registerService)
    {
        parent::__construct();
        $this->registerService = $registerService;
    }

    public function handle(): bool
    {
        $email = $this->argument('email');

        /** @var User $user */
        if (! $user = User::where('email', $email)->first()) {
            $this->error('Undefined user with email ' . $email);

            return false;
        }

        try {
            $this->registerService->verifyEmail($user->id);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());

            return false;
        }

        $this->info('User is successfully verified');

        return true;
    }
}
