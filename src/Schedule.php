<?php

declare(strict_types=1);

namespace App;

use App\Command\FetchFeedsCommand;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            
            // Fetch RSS feeds every hour at minute 0
            ->add('fetch-feeds', new FetchFeedsCommand(), cron: '0 * * * *')
            
            // Optional: Add a daily health check at 6 AM
            // ->add('health-check', new HealthCheckMessage(), cron: '0 6 * * *')
        ;
    }
}
