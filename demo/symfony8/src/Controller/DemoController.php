<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\CsrfOnlyType;
use App\Form\LoginType;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\RateLimiter\DeviceRateLimiterInterface;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Nowo\DeviceIntelligenceBundle\Trust\DeviceTrustService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

use function in_array;

/**
 * Multi-case demo: collect, checkout step-up, login, trust, privileged, coupon, export, alerts.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DemoController extends AbstractController
{
    private const LOCALE = 'en|es';

    private const CASE_PATHS = 'checkout|trust|privileged|coupon|export|alerts';

    /** @var list<string> */
    public const CASES = [
        'overview',
        'checkout',
        'login',
        'trust',
        'privileged',
        'coupon',
        'export',
        'alerts',
    ];

    public function __construct(
        private DeviceTrustService $trust,
        private DeviceRateLimiterInterface $limiter,
    ) {
    }

    #[Route('/', name: 'homepage_default', methods: ['GET'])]
    public function homeDefault(): Response
    {
        return $this->redirectToRoute('homepage', ['_locale' => 'en'], Response::HTTP_FOUND);
    }

    #[Route('/{_locale}', name: 'homepage', requirements: ['_locale' => self::LOCALE], methods: ['GET'])]
    public function home(Request $request, ?DeviceContext $device = null): Response
    {
        $legacy = $request->query->get('case');
        if (\is_string($legacy) && '' !== $legacy && 'overview' !== $legacy) {
            return $this->redirectToCase($request->getLocale(), $legacy);
        }

        return $this->renderCase($request, $device, 'overview');
    }

    #[Route(
        '/{_locale}/{case}',
        name: 'demo_case',
        requirements: ['_locale' => self::LOCALE, 'case' => self::CASE_PATHS],
        methods: ['GET'],
    )]
    public function casePage(Request $request, string $case, ?DeviceContext $device = null): Response
    {
        return $this->renderCase($request, $device, $case);
    }

    #[Route('/{_locale}/login', name: 'demo_login', requirements: ['_locale' => self::LOCALE], methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $auth, ?DeviceContext $device = null): Response
    {
        return $this->renderCase($request, $device, 'login', [], $auth);
    }

    #[Route('/logout', name: 'demo_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Handled by the security logout listener.');
    }

    #[Route('/{_locale}/checkout/pay', name: 'demo_checkout_pay', requirements: ['_locale' => self::LOCALE], methods: ['POST'])]
    public function checkoutPay(Request $request, ?DeviceContext $device = null): Response
    {
        $this->denyUnlessValidCsrfForm($request, 'demo_checkout_pay', 'demo.case.checkout.action', 'demo_checkout');

        $score = $device?->risk()->score() ?? 0;
        $locale = $request->getLocale();
        if ($score >= 90) {
            $this->addFlash('danger', 'demo.flash.checkout_blocked');
        } elseif ($score >= 70 || null === $device || $device->isNew()) {
            $this->addFlash('warning', 'demo.flash.checkout_step_up');
        } else {
            $this->addFlash('success', 'demo.flash.checkout_ok');
        }

        return $this->redirectToCase($locale, 'checkout');
    }

    #[Route('/{_locale}/trust', name: 'demo_trust', requirements: ['_locale' => self::LOCALE], methods: ['POST'])]
    public function trustDevice(Request $request, ?DeviceContext $device = null): Response
    {
        $this->denyUnlessValidCsrfForm($request, 'demo_trust', 'demo.case.trust.action', 'demo_trust', $this->trustFormOptions());
        $locale = $request->getLocale();
        $user = $this->getUser();
        if (null === $user) {
            $this->addFlash('warning', 'demo.flash.need_login');

            return $this->redirectToCase($locale, 'login');
        }
        if (null === $device) {
            $this->addFlash('warning', 'demo.flash.need_collect');

            return $this->redirectToCase($locale, 'trust');
        }

        $this->trust->trust(
            $device->device(),
            new UserIdentifier($user->getUserIdentifier()),
            new \DateTimeImmutable('+90 days'),
            'Demo · '.$user->getUserIdentifier(),
        );
        $this->addFlash('success', 'demo.flash.trusted');

        return $this->redirectToCase($locale, 'trust');
    }

    #[Route('/{_locale}/trust/revoke', name: 'demo_revoke', requirements: ['_locale' => self::LOCALE], methods: ['POST'])]
    public function revokeDevice(Request $request, ?DeviceContext $device = null): Response
    {
        $this->denyUnlessValidCsrfForm($request, 'demo_revoke', 'demo.case.trust.revoke', 'demo_revoke', $this->revokeFormOptions());
        $locale = $request->getLocale();
        $user = $this->getUser();
        if (null === $user) {
            $this->addFlash('warning', 'demo.flash.need_login');

            return $this->redirectToCase($locale, 'login');
        }
        if (null === $device) {
            $this->addFlash('warning', 'demo.flash.need_collect');

            return $this->redirectToCase($locale, 'trust');
        }

        $this->trust->revoke(
            $device->device(),
            new UserIdentifier($user->getUserIdentifier()),
        );
        $this->addFlash('success', 'demo.flash.revoked');

        return $this->redirectToCase($locale, 'trust');
    }

    #[Route('/{_locale}/privileged', name: 'demo_privileged', requirements: ['_locale' => self::LOCALE], methods: ['POST'])]
    public function privileged(Request $request, ?DeviceContext $device = null): Response
    {
        $this->denyUnlessValidCsrfForm($request, 'demo_privileged', 'demo.case.privileged.action', 'demo_privileged');
        $locale = $request->getLocale();
        if (null === $this->getUser()) {
            $this->addFlash('warning', 'demo.flash.need_login');

            return $this->redirectToCase($locale, 'login');
        }
        if (null === $device || !$device->isTrusted()) {
            $this->addFlash('danger', 'demo.flash.need_trust');

            return $this->redirectToCase($locale, 'privileged');
        }
        if ($device->risk()->score() > 70) {
            $this->addFlash('danger', 'demo.flash.privileged_risk');

            return $this->redirectToCase($locale, 'privileged');
        }

        $this->addFlash('success', 'demo.flash.privileged_ok');

        return $this->redirectToCase($locale, 'privileged');
    }

    #[Route('/{_locale}/coupon', name: 'demo_coupon', requirements: ['_locale' => self::LOCALE], methods: ['POST'])]
    public function coupon(Request $request, ?DeviceContext $device = null): Response
    {
        $this->denyUnlessValidCsrfForm($request, 'demo_coupon', 'demo.case.coupon.action', 'demo_coupon');
        $locale = $request->getLocale();
        $ok = $this->limiter->consume(
            'coupon',
            'device',
            hash('sha256', (string) $request->getClientIp()),
            $this->getUser()?->getUserIdentifier(),
            $device?->device()->id->value,
            3,
            '24 hours',
        );
        $this->addFlash($ok ? 'success' : 'danger', $ok ? 'demo.flash.coupon_ok' : 'demo.flash.coupon_blocked');

        return $this->redirectToCase($locale, 'coupon');
    }

    #[Route('/{_locale}/export', name: 'demo_export', requirements: ['_locale' => self::LOCALE], methods: ['POST'])]
    public function export(Request $request, ?DeviceContext $device = null): Response
    {
        $this->denyUnlessValidCsrfForm($request, 'demo_export', 'demo.case.export.action', 'demo_export');
        $locale = $request->getLocale();
        $score = $device?->risk()->score() ?? 0;
        if ($score > 89) {
            $this->addFlash('danger', 'demo.flash.export_blocked');
        } else {
            $this->addFlash('success', 'demo.flash.export_ok');
        }

        return $this->redirectToCase($locale, 'export');
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function renderCase(
        Request $request,
        ?DeviceContext $device,
        string $case,
        array $extra = [],
        ?AuthenticationUtils $auth = null,
    ): Response {
        $locale = $request->getLocale();

        return $this->render('demo/home.html.twig', [
            'case' => $case,
            'cases' => $this->caseNav($locale),
            'device' => $device,
            'suspicious' => $request->hasSession() ? $request->getSession()->get('demo.suspicious') : null,
            ...$this->formsForCase($case, $request, $auth),
            ...$extra,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formsForCase(string $case, Request $request, ?AuthenticationUtils $auth): array
    {
        $locale = $request->getLocale();

        return match ($case) {
            'checkout' => [
                'checkout_form' => $this->createActionForm($locale, 'demo_checkout_pay', 'demo.case.checkout.action', 'demo_checkout'),
            ],
            'login' => $this->loginPageVars($request, $auth),
            'trust' => [
                'trust_form' => $this->createActionForm($locale, 'demo_trust', 'demo.case.trust.action', 'demo_trust', $this->trustFormOptions()),
                'revoke_form' => $this->createActionForm($locale, 'demo_revoke', 'demo.case.trust.revoke', 'demo_revoke', $this->revokeFormOptions()),
            ],
            'privileged' => [
                'privileged_form' => $this->createActionForm($locale, 'demo_privileged', 'demo.case.privileged.action', 'demo_privileged'),
            ],
            'coupon' => [
                'coupon_form' => $this->createActionForm($locale, 'demo_coupon', 'demo.case.coupon.action', 'demo_coupon'),
            ],
            'export' => [
                'export_form' => $this->createActionForm($locale, 'demo_export', 'demo.case.export.action', 'demo_export'),
            ],
            default => [],
        };
    }

    /**
     * @return array{login_form: FormInterface<mixed>, login_error: \Symfony\Component\Security\Core\Exception\AuthenticationException|null}
     */
    private function loginPageVars(Request $request, ?AuthenticationUtils $auth): array
    {
        if (!$auth instanceof AuthenticationUtils) {
            throw new \LogicException('AuthenticationUtils required for login.');
        }

        return [
            'login_form' => $this->createLoginForm($request, $auth),
            'login_error' => $auth->getLastAuthenticationError(),
        ];
    }

    /**
     * @return list<array{id: string, url: string}>
     */
    private function caseNav(string $locale): array
    {
        $items = [];
        foreach (self::CASES as $id) {
            $items[] = [
                'id' => $id,
                'url' => $this->caseUrl($locale, $id),
            ];
        }

        return $items;
    }

    private function redirectToCase(string $locale, string $case): Response
    {
        if (!in_array($case, self::CASES, true)) {
            return $this->redirectToRoute('homepage', ['_locale' => $locale]);
        }

        return $this->redirect($this->caseUrl($locale, $case));
    }

    private function caseUrl(string $locale, string $case): string
    {
        return match ($case) {
            'login' => $this->generateUrl('demo_login', ['_locale' => $locale]),
            'overview' => $this->generateUrl('homepage', ['_locale' => $locale]),
            default => $this->generateUrl('demo_case', ['_locale' => $locale, 'case' => $case]),
        };
    }

    /**
     * @param array<string, mixed> $extraOptions
     *
     * @return FormInterface<mixed>
     */
    private function createActionForm(
        string $locale,
        string $route,
        string $submitLabel,
        string $csrfTokenId,
        array $extraOptions = [],
    ): FormInterface {
        return $this->createForm(CsrfOnlyType::class, null, [
            'action' => $this->generateUrl($route, ['_locale' => $locale]),
            'method' => 'POST',
            'csrf_token_id' => $csrfTokenId,
            'submit_label' => $submitLabel,
            ...$extraOptions,
        ]);
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createLoginForm(Request $request, AuthenticationUtils $auth): FormInterface
    {
        $last = $auth->getLastUsername();
        if ('' === $last) {
            $last = 'alice';
        }

        return $this->createForm(LoginType::class, null, [
            'action' => $this->generateUrl('demo_login', ['_locale' => $request->getLocale()]),
            'method' => 'POST',
            'last_username' => $last,
            'target_path' => $this->generateUrl('demo_login', ['_locale' => $request->getLocale()]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function trustFormOptions(): array
    {
        return [
            'attr' => ['class' => 'd-inline'],
            'submit_row_attr' => ['class' => 'd-inline-block mb-0 me-2'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revokeFormOptions(): array
    {
        return [
            'attr' => ['class' => 'd-inline'],
            'submit_attr' => ['class' => 'btn btn-outline-danger'],
            'submit_row_attr' => ['class' => 'd-inline-block mb-0'],
        ];
    }

    /**
     * @param array<string, mixed> $extraOptions
     */
    private function denyUnlessValidCsrfForm(
        Request $request,
        string $route,
        string $submitLabel,
        string $csrfTokenId,
        array $extraOptions = [],
    ): void {
        $form = $this->createActionForm($request->getLocale(), $route, $submitLabel, $csrfTokenId, $extraOptions);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
