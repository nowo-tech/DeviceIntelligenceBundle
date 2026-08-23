# Custom collectors

**Browser:** implement `Collector` (`name` + `collect()`) and `client.registerCollector(myCollector)`.

**Server:** implement `ServerSignalProviderInterface` and replace/decorate the default provider in DI. For reverse-proxy TLS/JA3 hints, implement `NetworkSignalProviderInterface` — PHP does not expose JA3 by itself.

Never send raw canvas pixels or audio PCM. Send compact digests only.
