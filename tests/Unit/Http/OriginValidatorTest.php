<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Http;

use Nowo\DeviceIntelligenceBundle\Http\Exception\CollectValidationException;
use Nowo\DeviceIntelligenceBundle\Http\OriginValidator;
use Nowo\DeviceIntelligenceBundle\Tests\Support\ProcessedConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class OriginValidatorTest extends TestCase
{
    public function testNoneSkipsChecks(): void
    {
        $validator = new OriginValidator(ProcessedConfig::object(['endpoint' => ['csrf' => 'none']]));
        $validator->validate(Request::create('/_device/collect', 'POST'));
        $this->addToAssertionCount(1);
    }

    public function testOriginAllowsSameHost(): void
    {
        $validator = new OriginValidator(ProcessedConfig::object());
        $request = Request::create('https://app.test/_device/collect', 'POST', server: [
            'HTTP_HOST' => 'app.test',
            'HTTP_ORIGIN' => 'https://app.test',
        ]);
        $validator->validate($request);
        $this->addToAssertionCount(1);
    }

    public function testOriginRejectsMissingHeader(): void
    {
        $validator = new OriginValidator(ProcessedConfig::object());
        $this->expectException(CollectValidationException::class);
        $validator->validate(Request::create('/_device/collect', 'POST'));
    }

    public function testOriginRejectsOtherHost(): void
    {
        $validator = new OriginValidator(ProcessedConfig::object());
        $request = Request::create('https://app.test/_device/collect', 'POST', server: [
            'HTTP_HOST' => 'app.test',
            'HTTP_ORIGIN' => 'https://evil.test',
        ]);
        $this->expectException(CollectValidationException::class);
        $validator->validate($request);
    }

    public function testAllowedOrigins(): void
    {
        $validator = new OriginValidator(ProcessedConfig::object([
            'endpoint' => ['allowed_origins' => ['https://cdn.example']],
        ]));
        $request = Request::create('https://app.test/_device/collect', 'POST', server: [
            'HTTP_HOST' => 'app.test',
            'HTTP_ORIGIN' => 'https://cdn.example',
        ]);
        $validator->validate($request);
        $this->addToAssertionCount(1);
    }

    public function testDoubleSubmit(): void
    {
        $validator = new OriginValidator(ProcessedConfig::object(['endpoint' => ['csrf' => 'double_submit']]));
        $request = Request::create('/_device/collect', 'POST');
        $request->headers->set('X-CSRF-Token', 'abc');
        $request->cookies->set('di_csrf', 'abc');
        $validator->validate($request);
        $this->addToAssertionCount(1);
    }

    public function testDoubleSubmitMismatch(): void
    {
        $validator = new OriginValidator(ProcessedConfig::object(['endpoint' => ['csrf' => 'double_submit']]));
        $request = Request::create('/_device/collect', 'POST');
        $request->headers->set('X-CSRF-Token', 'abc');
        $request->cookies->set('di_csrf', 'xyz');
        $this->expectException(CollectValidationException::class);
        $validator->validate($request);
    }
}
