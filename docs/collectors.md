# Collectors

Client collectors live in this package (`src/Resources/assets`). The server never trusts them as facts.

| Collector | Default | Entropy | Notes |
| --- | --- | --- | --- |
| navigator | on | low–medium | platform, concurrency, memory, languages |
| clientHints | on | medium | UA-CH JS values; headers are a server concern |
| screen | on | medium | class only after normalize |
| timezone | on | low | IANA id |
| capabilities | on | medium | boolean set |
| canvas | on | high | digest only, no pixels |
| webgl | on | high | vendor family + limits hash |
| audio | on | high | OfflineAudioContext, no microphone |
| automation | on | n/a (risk only) | heuristic confidence, not a boolean |
| fonts | **off** | high | opt-in |

`privacy.mode: strict` drops audio, canvas, webgl, and fonts even if enabled in the profile.

Custom collectors: `client.registerCollector(myCollector)` on the SDK. Server-side: implement `ServerSignalProviderInterface` / `NetworkSignalProviderInterface`.
