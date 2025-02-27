<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\profilepage\Helpers;

use SimpleSAML\Module\profilepage\Entities\Authentication\Event\State\Oidc;
use SimpleSAML\Module\profilepage\Entities\Authentication\Event\State\Saml2;
use SimpleSAML\Module\profilepage\Entities\Bases\AbstractState;
use SimpleSAML\Module\profilepage\Exceptions\StateException;
use SimpleSAML\Module\profilepage\Helpers\AuthenticationEventStateResolver;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Test\Module\profilepage\Constants\StateArrays;

/**
 * @covers \SimpleSAML\Module\profilepage\Helpers\AuthenticationEventStateResolver
 * @uses \SimpleSAML\Module\profilepage\Entities\Bases\AbstractState
 * @uses \SimpleSAML\Module\profilepage\Entities\Authentication\Event\State\Saml2
 * @uses \SimpleSAML\Module\profilepage\Entities\Authentication\Event\State\Oidc
 * @uses \SimpleSAML\Module\profilepage\Services\HelpersManager
 * @uses \SimpleSAML\Module\profilepage\Helpers\Network
 */
class AuthenticationEventStateResolverTest extends TestCase
{
    /**
     * @throws StateException
     */
    public function testCanResolveState(): void
    {
        $resolver = new AuthenticationEventStateResolver();

        $this->assertInstanceOf(Saml2::class, $resolver->fromStateArray(StateArrays::SAML2_FULL));
        $this->assertInstanceOf(Oidc::class, $resolver->fromStateArray(StateArrays::OIDC_FULL));
    }

    public function testThrowsIfAttributesNotSet(): void
    {
        $resolver = new AuthenticationEventStateResolver();

        $stateArray = StateArrays::SAML2_FULL;
        unset($stateArray[AbstractState::KEY_ATTRIBUTES]);

        $this->expectException(StateException::class);

        $resolver->fromStateArray($stateArray);
    }

    public function testThrowsForInvalidStateArray(): void
    {
        $resolver = new AuthenticationEventStateResolver();

        $stateArray = StateArrays::SAML2_FULL;
        unset($stateArray[Saml2::KEY_IDENTITY_PROVIDER_METADATA]);
        unset($stateArray[Saml2::KEY_SOURCE]);

        $this->expectException(StateException::class);

        $resolver->fromStateArray($stateArray);
    }
}
