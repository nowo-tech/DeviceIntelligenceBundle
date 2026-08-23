<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching\Candidate;

use DateInterval;
use DateTimeImmutable;
use Nowo\DeviceIntelligence\Matching\IndexKeyFactory;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;

final class RepositoryCandidateProvider implements DeviceCandidateProviderInterface
{
    public function __construct(
        private DeviceRepositoryInterface $devices,
        private IndexKeyFactory $indexKeys = new IndexKeyFactory(),
        private int $limit = 64,
        private string $lookback = 'P180D',
    ) {
    }

    public function candidates(DeviceObservation $observation): iterable
    {
        $key   = $this->indexKeys->fromSignals($observation->signals);
        $since = $observation->createdAt->sub(new DateInterval($this->lookback));
        $found = $this->devices->findCandidates($key->osFamily, $key->browserFamily, $key->timezone, $key->gpuFamily, $this->limit, $since);
        if ($found !== []) {
            return $found;
        }
        $found = $this->devices->findCandidates($key->osFamily, $key->browserFamily, null, null, $this->limit, $since);
        if ($found !== []) {
            return $found;
        }

        return $this->devices->findCandidates($key->osFamily, $key->browserFamily, null, null, $this->limit, new DateTimeImmutable('1970-01-01'));
    }
}
