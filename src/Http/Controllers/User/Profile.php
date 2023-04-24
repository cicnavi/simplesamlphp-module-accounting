<?php

declare(strict_types=1);

namespace SimpleSAML\Module\accounting\Http\Controllers\User;

use Psr\Log\LoggerInterface;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration as SspConfiguration;
use SimpleSAML\Error\ConfigurationError;
use SimpleSAML\Error\CriticalConfigurationError;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Locale\Translate;
use SimpleSAML\Module\accounting\Entities\Authentication\Protocol\Oidc;
use SimpleSAML\Module\accounting\Entities\ConnectedServiceProvider;
use SimpleSAML\Module\accounting\Entities\User;
use SimpleSAML\Module\accounting\Exceptions\Exception;
use SimpleSAML\Module\accounting\Exceptions\InvalidConfigurationException;
use SimpleSAML\Module\accounting\Helpers\Attributes;
use SimpleSAML\Module\accounting\Helpers\Routes;
use SimpleSAML\Module\accounting\ModuleConfiguration;
use SimpleSAML\Module\accounting\ModuleConfiguration\ConnectionType;
use SimpleSAML\Module\accounting\Providers\Builders\AuthenticationDataProviderBuilder;
use SimpleSAML\Module\accounting\Providers\Interfaces\AuthenticationDataProviderInterface;
use SimpleSAML\Module\accounting\Services\AlertsBag;
use SimpleSAML\Module\accounting\Services\CsrfToken;
use SimpleSAML\Module\accounting\Services\HelpersManager;
use SimpleSAML\Module\accounting\Services\MenuManager;
use SimpleSAML\Module\accounting\Services\SspModuleManager;
use SimpleSAML\Session;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @psalm-suppress UnusedClass Used as route controller.
 */
class Profile
{
    protected ModuleConfiguration $moduleConfiguration;
    protected SspConfiguration $sspConfiguration;
    protected Session $session;
    protected LoggerInterface $logger;
    protected string $defaultAuthenticationSource;
    protected Simple $authSimple;
    protected AuthenticationDataProviderBuilder $authenticationDataProviderBuilder;
    protected HelpersManager $helpersManager;
    protected SspModuleManager $sspModuleManager;
    protected User $user;
    protected MenuManager $menuManager;
    protected CsrfToken $csrfToken;
    protected AlertsBag $alertsBag;

    /**
     * @param ModuleConfiguration $moduleConfiguration
     * @param SspConfiguration $sspConfiguration
     * @param Session $session The current user session.
     * @param LoggerInterface $logger
     * @param Simple|null $authSimple
     * @param AuthenticationDataProviderBuilder|null $authenticationDataProviderBuilder
     * @param HelpersManager|null $helpersManager
     * @param SspModuleManager|null $sspModuleManager
     * @param CsrfToken|null $csrfToken
     * @param AlertsBag|null $alertsBag
     */
    public function __construct(
        ModuleConfiguration $moduleConfiguration,
        SspConfiguration $sspConfiguration,
        Session $session,
        LoggerInterface $logger,
        Simple $authSimple = null,
        AuthenticationDataProviderBuilder $authenticationDataProviderBuilder = null,
        HelpersManager $helpersManager = null,
        SspModuleManager $sspModuleManager = null,
        CsrfToken $csrfToken = null,
        AlertsBag $alertsBag = null
    ) {
        $this->moduleConfiguration = $moduleConfiguration;
        $this->sspConfiguration = $sspConfiguration;
        $this->session = $session;
        $this->logger = $logger;

        $this->defaultAuthenticationSource = $moduleConfiguration->getDefaultAuthenticationSource();
        $this->authSimple = $authSimple ?? new Simple($this->defaultAuthenticationSource, $sspConfiguration, $session);

        $this->helpersManager = $helpersManager ?? new HelpersManager();

        $this->authenticationDataProviderBuilder = $authenticationDataProviderBuilder ??
            new AuthenticationDataProviderBuilder($this->moduleConfiguration, $this->logger, $this->helpersManager);

        $this->sspModuleManager = $sspModuleManager ?? new SspModuleManager($this->logger, $this->helpersManager);

        // Make sure the end user is authenticated.
        $this->authSimple->requireAuth();
        $this->user = new User($this->authSimple->getAttributes());
        $this->menuManager = $this->prepareMenuManager();
        $this->csrfToken = $csrfToken ?? new CsrfToken($this->session, $this->helpersManager);
        $this->alertsBag = $alertsBag ?? new AlertsBag($this->session);
    }

    /**
     * @throws ConfigurationError
     */
    public function personalData(): Response
    {
        $normalizedAttributes = [];

        $toNameAttributeMap = $this->prepareToNameAttributeMap();

        /**
         * @var string $name
         * @var string[] $value
         */
        foreach ($this->user->getAttributes() as $name => $value) {
            // Convert attribute names to user-friendly names.
            if (array_key_exists($name, $toNameAttributeMap)) {
                $name = (string)$toNameAttributeMap[$name];
            }
            $normalizedAttributes[$name] = implode('; ', $value);
        }

        $template = $this->resolveTemplate('accounting:user/personal-data.twig');
        $template->data += compact('normalizedAttributes');

        return $template;
    }

    /**
     * @throws Exception
     * @throws ConfigurationError
     */
    public function connectedOrganizations(): Template
    {
        $userIdentifier = $this->resolveUserIdentifier();

        $authenticationDataProvider = $this->resolveAuthenticationDataProvider();

        $connectedServiceProviderBag = $authenticationDataProvider->getConnectedServiceProviders($userIdentifier);

        $oidc = $this->sspModuleManager->getOidc();
        $accessTokensByClient = [];
        $refreshTokensByClient = [];
        $oidcProtocolDesignation = Oidc::DESIGNATION;

        // If oidc module is enabled, gather users access and refresh tokens for particular OIDC service providers.
        if ($oidc->isEnabled()) {
            // Filter out OIDC service providers and get their entity (client) IDs.
            $oidcClientIds = array_map(
                function (ConnectedServiceProvider $connectedServiceProvider) {
                    return $connectedServiceProvider->getServiceProvider()->getEntityId();
                },
                array_filter(
                    $connectedServiceProviderBag->getAll(),
                    function (ConnectedServiceProvider $connectedServiceProvider) {
                        return $connectedServiceProvider->getServiceProvider()->getProtocol()->getDesignation() ===
                            Oidc::DESIGNATION;
                    }
                )
            );

            if (! empty($oidcClientIds)) {
                $accessTokensByClient = $this->helpersManager->getArr()->groupByValue(
                    $oidc->getUsersAccessTokens($userIdentifier, $oidcClientIds),
                    'client_id'
                );

                $refreshTokensByClient = $this->helpersManager->getArr()->groupByValue(
                    $oidc->getUsersRefreshTokens($userIdentifier, $oidcClientIds),
                    'client_id'
                );
            }
            //die(var_dump($oidcClientIds, $accessTokensByClient, $refreshTokensByClient));
        }

        $template = $this->resolveTemplate('accounting:user/connected-organizations.twig');
        $template->data += compact(
            'connectedServiceProviderBag',
            'accessTokensByClient',
            'refreshTokensByClient',
            'oidcProtocolDesignation'
        );

        return $template;
    }

    /**
     * @throws Exception
     * @throws ConfigurationError
     */
    public function activity(Request $request): Template
    {
        $userIdentifier = $this->resolveUserIdentifier();

        $authenticationDataProvider = $this->resolveAuthenticationDataProvider();

        $page = ($page = (int)$request->query->get('page', 1)) > 0 ? $page : 1;

        $maxResults = 10;
        $firstResult = ($page - 1) * $maxResults;

        $activityBag = $authenticationDataProvider->getActivity($userIdentifier, $maxResults, $firstResult);

        $template = $this->resolveTemplate('accounting:user/activity.twig');
        $template->data += compact('activityBag', 'page', 'maxResults');

        return $template;
    }

    /**
     * @throws Exception|ConfigurationError
     */
    public function oidcTokens(): Response
    {
        $oidc = $this->sspModuleManager->getOidc();

        // If oidc module is not enabled, this route should not be called.
        if (!$oidc->isEnabled()) {
            return new RedirectResponse($this->helpersManager->getRoutes()->getUrl(Routes::PATH_USER_PERSONAL_DATA));
        }

        $userIdentifier = $this->resolveUserIdentifier();

        $accessTokensByClient = $this->helpersManager->getArr()->groupByValue(
            $oidc->getUsersAccessTokens($userIdentifier),
            'client_id'
        );

        $refreshTokensByClient = $this->helpersManager->getArr()->groupByValue(
            $oidc->getUsersRefreshTokens($userIdentifier),
            'client_id'
        );

        $clientIds = array_unique(array_merge(array_keys($accessTokensByClient), array_keys($refreshTokensByClient)));
        $clients = $this->helpersManager->getArr()->groupByValue(
            $oidc->getClients($clientIds),
            'id'
        );

        //die(var_dump($accessTokensByClient, $refreshTokensByClient, $clientIds, $clients));

        $template = $this->resolveTemplate('accounting:user/oidc-tokens.twig');
        $template->data += compact('accessTokensByClient', 'refreshTokensByClient', 'clients');

        return $template;
    }

    public function oidcTokenRevoke(Request $request): Response
    {
        $oidc = $this->sspModuleManager->getOidc();

        // If oidc module is not enabled, this route should not be called.
        if (! $oidc->isEnabled()) {
            return new RedirectResponse($this->helpersManager->getRoutes()->getUrl(Routes::PATH_USER_PERSONAL_DATA));
        }

        $redirectTo = (string) $request->query->get(Routes::QUERY_REDIRECT_TO_PATH, Routes::PATH_USER_OIDC_TOKENS);

        $response = new RedirectResponse(
            $this->helpersManager->getRoutes()->getUrl($redirectTo)
        );

        if (! $this->csrfToken->validate($request->request->getAlnum('csrf-token'))) {
            $this->alertsBag->put(
                new AlertsBag\Alert('Could not verify CSRF token.', 'warning')
            );

            return $response;
        }

        $validTokenTypes = ['access', 'refresh'];

        $tokenType = $request->request->getAlnum('token-type');

        if (! in_array($tokenType, $validTokenTypes)) {
            $this->alertsBag->put(
                new AlertsBag\Alert('Token type not valid.', 'warning')
            );
            return $response;
        }

        $tokenId = $request->request->getAlnum('token-id');

        $userIdentifier = $this->resolveUserIdentifier();

        if ($tokenType === 'access') {
            $oidc->revokeUsersAccessToken($userIdentifier, $tokenId);
        } elseif ($tokenType === 'refresh') {
            $oidc->revokeUsersRefreshToken($userIdentifier, $tokenId);
        }

        $this->alertsBag->put(
            new AlertsBag\Alert('Token revoked successfully.', 'success')
        );

        return $response;
    }

    public function oidcTokenRevokeXhr(Request $request): Response
    {

        $oidc = $this->sspModuleManager->getOidc();
        $response = new JsonResponse();


        // If oidc module is not enabled, this route should not be called.
        if (! $oidc->isEnabled()) {
            return new JsonResponse(['status' => 'error', 'message' => 'Not available.'], 404);
        }

        if (! $this->csrfToken->validate((string) $request->cookies->get(CsrfToken::KEY))) {
            $this->appendCsrfCookie($response);
            return $response
                ->setData(['status' => 'error', 'message' => 'CSRF validation failed.'])
                ->setStatusCode(400);
        }

        $this->appendCsrfCookie($response);

        $validTokenTypes = ['access', 'refresh'];

        $tokenType = $request->request->getAlnum('token-type');

        if (! in_array($tokenType, $validTokenTypes)) {
            return $response
                ->setData(['status' => 'error', 'message' => 'Token type not valid.'])
                ->setStatusCode(422);
        }

        $tokenId = $request->request->getAlnum('token-id');

        $userIdentifier = $this->resolveUserIdentifier();

        if ($tokenType === 'access') {
            $oidc->revokeUsersAccessToken($userIdentifier, $tokenId);
        } elseif ($tokenType === 'refresh') {
            $oidc->revokeUsersRefreshToken($userIdentifier, $tokenId);
        }

        return $response
            ->setData(['status' => 'success', 'message' => 'Token revoked successfully.']);
    }

    /**
     * @throws Exception
     */
    protected function resolveUserIdentifier(): string
    {
        $userIdAttributeName = $this->moduleConfiguration->getUserIdAttributeName();
        $userIdentifier = $this->user->getFirstAttributeValue($userIdAttributeName);

        if (is_null($userIdentifier)) {
            $message = sprintf('No identifier %s present in user attributes.', $userIdAttributeName);
            throw new Exception($message);
        }

        return $userIdentifier;
    }

    /**
     * @throws Exception
     */
    protected function resolveAuthenticationDataProvider(): AuthenticationDataProviderInterface
    {
        return $this->authenticationDataProviderBuilder
            ->build(
                $this->moduleConfiguration->getDefaultDataTrackerAndProviderClass(),
                ConnectionType::SLAVE
            );
    }

    public function logout(): Response
    {
        return new RunnableResponse([$this->authSimple, 'logout'], [$this->getLogoutUrl()]);
    }

    protected function getLogoutUrl(): string
    {
        try {
            return $this->sspConfiguration->getBasePath() . 'logout.php';
        } catch (CriticalConfigurationError $exception) {
            $message = \sprintf('Could not resolve SimpleSAMLphp base path. Error was: %s', $exception->getMessage());
            throw new InvalidConfigurationException($message, $exception->getCode(), $exception);
        }
    }

    /**
     * Load all attribute map files which translate attribute names to user-friendly name format.
     */
    protected function prepareToNameAttributeMap(): array
    {
        return $this->helpersManager->getAttributes()->getMergedAttributeMapForFiles(
            $this->sspConfiguration->getBaseDir(),
            Attributes::MAP_FILES_TO_NAME
        );
    }

    /**
     * @throws ConfigurationError
     */
    protected function resolveTemplate(string $template): Template
    {
        $templateInstance = new Template($this->sspConfiguration, $template);

        $templateInstance->getLocalization()->addModuleDomain(ModuleConfiguration::MODULE_NAME);
        $templateInstance->getLocalization()->addAttributeDomains();

        $templateInstance->data = [
            'menuManager' => $this->menuManager,
            'csrfToken' => $this->csrfToken,
            'alertsBag' => $this->alertsBag,
        ];

        // Make CSRF token also available as a cookie, so it can be used for XHR POST requests validation.
        $this->appendCsrfCookie($templateInstance);

        return $templateInstance;
    }

    protected function prepareMenuManager(): MenuManager
    {
        $menuManager = new MenuManager();

        $menuManager->addItems(
            new MenuManager\MenuItem(
                'personal-data',
                Translate::noop('Personal Data'),
                'css/src/icons/prof-page.svg'
            ),
            new MenuManager\MenuItem(
                'connected-organizations',
                Translate::noop('Connected Organizations'),
                'css/src/icons/conn-orgs.svg'
            ),
            new MenuManager\MenuItem(
                'activity',
                Translate::noop('Activity'),
                'css/src/icons/activity.svg'
            )
        );

        // Depending on other functionalities, add additional menu items.
        if ($this->sspModuleManager->getOidc()->isEnabled()) {
            $menuManager->addItems(
                new MenuManager\MenuItem(
                    'oidc-tokens',
                    Translate::noop('Tokens'),
                    'css/src/icons/activity.svg'
                )
            );
        }

        $menuManager->addItems(
            new MenuManager\MenuItem(
                'logout',
                Translate::noop('Log out'),
                'css/src/icons/logout.svg'
            )
        );

        return $menuManager;
    }

    protected function appendCsrfCookie(Response $response): void
    {
        $response->headers->setCookie(new Cookie(CsrfToken::KEY, $this->csrfToken->get()));
    }
}
