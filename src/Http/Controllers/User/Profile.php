<?php

namespace SimpleSAML\Module\accounting\Http\Controllers\User;

use Psr\Log\LoggerInterface;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration as SspConfiguration;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Module\accounting\Exceptions\Exception;
use SimpleSAML\Module\accounting\Helpers\AttributesHelper;
use SimpleSAML\Module\accounting\ModuleConfiguration;
use SimpleSAML\Module\accounting\ModuleConfiguration\ConnectionType;
use SimpleSAML\Module\accounting\Providers\Builders\AuthenticationDataProviderBuilder;
use SimpleSAML\Module\accounting\Providers\Interfaces\AuthenticationDataProviderInterface;
use SimpleSAML\Session;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @psalm-suppress all TODO mivanci remove this psalm suppress after testing
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

    /**
     * @param ModuleConfiguration $moduleConfiguration
     * @param SspConfiguration $sspConfiguration
     * @param Session $session The current user session.
     * @param LoggerInterface $logger
     * @param Simple|null $authSimple
     * @param AuthenticationDataProviderBuilder|null $authenticationDataProviderBuilder
     */
    public function __construct(
        ModuleConfiguration $moduleConfiguration,
        SspConfiguration $sspConfiguration,
        Session $session,
        LoggerInterface $logger,
        Simple $authSimple = null,
        AuthenticationDataProviderBuilder $authenticationDataProviderBuilder = null
    ) {
        $this->moduleConfiguration = $moduleConfiguration;
        $this->sspConfiguration = $sspConfiguration;
        $this->session = $session;
        $this->logger = $logger;

        $this->defaultAuthenticationSource = $moduleConfiguration->getDefaultAuthenticationSource();
        $this->authSimple = $authSimple ?? new Simple($this->defaultAuthenticationSource, $sspConfiguration, $session);

        $this->authenticationDataProviderBuilder = $authenticationDataProviderBuilder ??
            new AuthenticationDataProviderBuilder($this->moduleConfiguration, $this->logger);

        // Make sure the end user is authenticated.
        $this->authSimple->requireAuth();
    }

    public function personalData(Request $request): Response
    {
        $normalizedAttributes = [];

        $toNameAttributeMap = $this->prepareToNameAttributeMap();

        /**
         * @var string $name
         * @var string[] $value
         */
        foreach ($this->authSimple->getAttributes() as $name => $value) {
            // Convert attribute names to user-friendly names.
            if (array_key_exists($name, $toNameAttributeMap)) {
                $name = $toNameAttributeMap[$name];
            }
            $normalizedAttributes[$name] = implode('; ', $value);
        }

        $template = $this->resolveTemplate('accounting:user/personal-data.twig');
        $template->data = compact('normalizedAttributes');

        return $template;
    }

    public function connectedOrganizations(Request $request): Template
    {
        $userIdentifier = $this->resolveUserIdentifier();

        $authenticationDataProvider = $this->resolveAuthenticationDataProvider();

        $this->removeDebugDisplayLimits();
        $connectedServiceProviderBag = $authenticationDataProvider->getConnectedServiceProviders($userIdentifier);

        $template = $this->resolveTemplate('accounting:user/connected-organizations.twig');
        $template->data = compact('connectedServiceProviderBag');

        return $template;
    }

    public function activity(Request $request): Template
    {
        $userIdentifier = $this->resolveUserIdentifier();

        $authenticationDataProvider = $this->resolveAuthenticationDataProvider();

        $page = ($page = (int)$request->query->get('page', 1)) > 0 ? $page : 1;

        // TODO mivanci make maxResults configurable
        $maxResults = 10;
        $firstResult = ($page - 1) * $maxResults;

        $this->removeDebugDisplayLimits();
        $activityBag = $authenticationDataProvider->getActivity($userIdentifier, $maxResults, $firstResult);

        $template = $this->resolveTemplate('accounting:user/activity.twig');
        $template->data = compact('activityBag');

        return $template;
    }

    protected function resolveUserIdentifier(): string
    {
        $attributes = $this->authSimple->getAttributes();
        $idAttributeName = $this->moduleConfiguration->getUserIdAttributeName();

        if (empty($attributes[$idAttributeName]) || !is_array($attributes[$idAttributeName])) {
            $message = sprintf('No identifier %s present in user attributes.', $idAttributeName);
            throw new Exception($message);
        }

        return (string)reset($attributes[$idAttributeName]);
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
        // TODO mivanci make logout button available using HTTP POST
        return new RunnableResponse([$this->authSimple, 'logout'], [$this->getLogoutUrl()]);
    }

    protected function getLogoutUrl(): string
    {
        return $this->sspConfiguration->getBasePath() . 'logout.php';
    }

    /**
     * Load all attribute map files which translate attribute names to user-friendly name format.
     */
    protected function prepareToNameAttributeMap(): array
    {
        return AttributesHelper::getMergedAttributeMapForFiles(
            $this->sspConfiguration->getBaseDir(),
            AttributesHelper::MAP_FILES_TO_NAME
        );
    }

    /** TODO mivanci remove after debugging */
    protected function removeDebugDisplayLimits(): void
    {
        ini_set('xdebug.var_display_max_depth', -1);
        ini_set('xdebug.var_display_max_children', -1);
        ini_set('xdebug.var_display_max_data', -1);
    }

    protected function resolveTemplate(string $template): Template
    {
        $templateInstance = new Template($this->sspConfiguration, $template);

        $templateInstance->getLocalization()->addModuleDomain(ModuleConfiguration::MODULE_NAME);
        $templateInstance->getLocalization()->addAttributeDomains();

        return $templateInstance;
    }
}
