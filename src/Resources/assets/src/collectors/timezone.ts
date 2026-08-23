import type { Collector, CollectorContext, Signal } from '../types/index';
import { createSignal, throwIfAborted } from './collector';

/**
 * IANA timezone and UTC offset. Offset is informational; identity uses the name.
 */
export class TimezoneCollector implements Collector {
  readonly name = 'timezone';

  async collect(ctx: CollectorContext): Promise<Signal> {
    throwIfAborted(ctx);

    let timeZone = 'UTC';
    try {
      timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
      timeZone = 'UTC';
    }

    const offsetMinutes = new Date().getTimezoneOffset();
    const value = {
      timeZone,
      offsetMinutes,
    };

    return createSignal({
      name: this.name,
      value,
      normalizedValue: timeZone,
      quality: timeZone.length > 0 ? 0.99 : 0.3,
      stability: 0.85,
      entropyCategory: 'low',
    });
  }
}
