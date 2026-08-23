# Custom risk rules

```php
use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligenceBundle\Attribute\AsDeviceRiskRule;

#[AsDeviceRiskRule]
final class TooManyTrialsRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'too_many_trials';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        return new RiskResult(0, $this->name());
    }
}
```

Enable and weight the rule under `profiles.*.risk.rules`.
