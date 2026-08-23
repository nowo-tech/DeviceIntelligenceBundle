# Quick start (under 10 minutes)

1. **Install**

   ```bash
   composer require nowo-tech/device-intelligence-bundle
   ```

2. **Enable** in `config/bundles.php` and add:

   ```yaml
   # config/packages/nowo_device_intelligence.yaml
   nowo_device_intelligence:
       doctrine:
           enabled: true
   ```

3. **Schema** — update Doctrine so `device_intelligence_*` tables exist.

4. **Collect** — include the IIFE (`src/Resources/public/js/device-intelligence.min.js`, published by `assets:install`) and POST to `/_device/collect` with `v`, `timestamp`, `nonce`, and `signals`.

5. **Use risk** in a controller:

   ```php
   public function checkout(\Nowo\DeviceIntelligenceBundle\Request\DeviceContext $device): Response
   {
       if ($device->risk()->score() >= 70) {
           // step-up MFA — do not block solely on device id
       }
       return new Response('ok');
   }
   ```

6. **Optional** — open the Web Profiler panel **Device Intelligence** after a collect request.

You now have a device ULID, a confidence score, and an explainable risk score. Treat them as signals, not credentials.
