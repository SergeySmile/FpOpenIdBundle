<?php
namespace Fp\OpenIdBundle\Tests\Security\Http\Firewall;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;

use PHPUnit\Framework\TestCase;

use Fp\OpenIdBundle\Security\Http\Firewall\OpenIdAuthenticationListener;
use Fp\OpenIdBundle\RelyingParty\IdentityProviderResponse;
use Fp\OpenIdBundle\Security\Core\Authentication\Token\OpenIdToken;

class OpenIdAuthenticationListenerTest extends TestCase
{
    /**
     * @test
     */
    public function couldBeConstructedWithRequiredSetOfArguments()
    {
        $listener = new OpenIdAuthenticationListener(
            $this->createTokenStorageMock(),
            $this->createAuthenticationManagerMock(),
            $this->createSessionAuthenticationStrategyMock(),
            $this->createHttpUtilsMock(),
            'providerKey',
            $this->createAuthenticationSuccessHandlerMock(),
            $this->createAuthenticationFailureHandlerMock(),
            $options = array()
        );
    }

    /**
     * @test
     */
    public function shouldNotContinueAuthenticationIfCheckRequestPathReturnFalse()
    {
        $httpUtilsMock = $this->createHttpUtilsMock();
        $httpUtilsMock
            ->expects($this->once())
            ->method('checkRequestPath')
            ->will($this->returnValue(false))
        ;

        $eventMock = $this->createGetResponseEventStub($this->createRequestMock());
        $eventMock
            ->expects($this->never())
            ->method('setResponse')
        ;

        $listener = new OpenIdAuthenticationListener(
            $this->createTokenStorageMock(),
            $this->createAuthenticationManagerMock(),
            $this->createSessionAuthenticationStrategyMock(),
            $httpUtilsMock,
            'providerKey',
            $this->createAuthenticationSuccessHandlerMock(),
            $this->createAuthenticationFailureHandlerMock(),
            $options = array()
        );

        $listener->handle($eventMock);
    }

    /**
     * @test
     *
     * @expectedException RuntimeException
     * @expectedExceptionMessage The relying party is required for the listener work, but it was not set. Seems like miss configuration
     */
    public function throwIfRelyingPartyNotSet()
    {
        $eventMock = $this->createGetResponseEventStub($this->createRequestMock());

        $listener = new OpenIdAuthenticationListener(
            $this->createTokenStorageMock(),
            $this->createAuthenticationManagerMock(),
            $this->createSessionAuthenticationStrategyMock(),
            $this->createHttpUtilsStub($checkRequestPathReturn = true),
            'providerKey',
            $this->createAuthenticationSuccessHandlerMock(),
            $this->createAuthenticationFailureHandlerMock(),
            $options = array()
        );

        $listener->handle($eventMock);
    }

    /**
     * @test
     */
    public function shouldNotContinueAuthenticationIfRelyingPartySupportsReturnFalse()
    {
        $relyingPartyMock = $this->createRelyingPartyMock();
        $relyingPartyMock
            ->expects($this->once())
            ->method('supports')
            ->will($this->returnValue(false))
        ;

        $eventMock = $this->createGetResponseEventStub($this->createRequestMock());
        $eventMock
            ->expects($this->never())
            ->method('setResponse')
        ;

        $listener = new OpenIdAuthenticationListener(
            $this->createTokenStorageMock(),
            $this->createAuthenticationManagerMock(),
            $this->createSessionAuthenticationStrategyMock(),
            $this->createHttpUtilsStub($checkRequestPathReturn = true),
            'providerKey',
            $this->createAuthenticationSuccessHandlerMock(),
            $this->createAuthenticationFailureHandlerMock(),
            $options = array()
        );

        $listener->setRelyingParty($relyingPartyMock);

        $listener->handle($eventMock);
    }

    /**
     * @test
     */
    public function shouldDuplicateRequestAndPassItToRelyingPartyManageMethod()
    {
        $requestMock = $this->createRequestStub(
            $hasSessionReturn = true,
            $hasPreviousSessionReturn = true,
            $duplicatedRequestMock = $this->createRequestMock()
        );

        $relyingPartyMock = $this->createRelyingPartyMock();
        $relyingPartyMock
            ->expects($this->any())
            ->method('supports')
            ->will($this->returnValue(true))
        ;
        $relyingPartyMock
            ->expects($this->once())
            ->method('manage')
            ->with($this->equalTo($duplicatedRequestMock))
            ->will($this->returnValue(new RedirectResponse('http://example.com/openid-provider')))
        ;

        $eventMock = $this->createGetResponseEventStub($requestMock);

        $listener = new OpenIdAuthenticationListener(
            $this->createTokenStorageMock(),
            $this->createAuthenticationManagerMock(),
            $this->createSessionAuthenticationStrategyMock(),
            $this->createHttpUtilsStub($checkRequestPathReturn = true),
            'providerKey',
            $this->createAuthenticationSuccessHandlerMock(),
            $this->createAuthenticationFailureHandlerMock(),
            $options = array()
        );

        $listener->setRelyingParty($relyingPartyMock);

        $listener->handle($eventMock);
    }

    /**
     * @test
     */
    public function shouldAddRequiredAttributesToDuplicatedRequest()
    {
        $expectedRequiredAttributes = array(
            'foo' => 'foo',
            'bar' => 'bar'
        );

        $duplicatedRequestMock = $this->createRequestMock();
        $duplicatedRequestMock->attributes = new ParameterBag();

        $requestMock = $this->createRequestStub(
            $hasSessionReturn = true,
            $hasPreviousSessionReturn = true,
            $duplicateReturn = $duplicatedRequestMock
        );

        $relyingPartyMock = $this->createRelyingPartyStub(
            $supportsReturn = true,
            $manageReturn = new RedirectResponse('http://example.com/openid-provider')
        );

        $eventMock = $this->createGetResponseEventStub($requestMock);

        $listener = new OpenIdAuthenticationListener(
            $this->createTokenStorageMock(),
            $this->createAuthenticationManagerMock(),
            $this->createSessionAuthenticationStrategyMock(),
            $this->createHttpUtilsStub($checkRequestPathReturn = true),
            'providerKey',
            $this->createAuthenticationSuccessHandlerMock(),
            $this->createAuthenticationFailureHandlerMock(),
            $options = array('required_attributes' => $expectedRequiredAttributes)
        );

        $listener->setRelyingParty($relyingPartyMock);

        $listener->handle($eventMock);

        $this->assertSame(
            $expectedRequiredAttributes,
            $duplicatedRequestMock->attributes->get('required_attributes')
        );
    }

    /**
     * @test
     */
    public function shouldAddOptionalAttributesToDuplicatedRequest()
    {
        $expectedOptionalAttributes = array(
            'foo' => 'foo',
            'bar' => 'bar'
        );

        $duplicatedRequestMock = $this->createRequestMock();
        $duplicatedRequestMock->attributes = new ParameterBag();

        $requestMock = $this->createRequestStub(
            $hasSessionReturn = true,
            $hasPreviousSessionReturn = true,
            $duplicateReturn = $duplicatedRequestMock
        );

        $relyingPartyMock = $this->createRelyingPartyStub(
            $supportsReturn = true,
            $manageReturn = new RedirectResponse('http://example.com/openid-provider')
        );

        $eventMock = $this->createGetResponseEventStub($requestMock);

        $listener = new OpenIdAuthenticationListener(
            $this->createTokenStorageMock(),
            $this->createAuthenticationManagerMock(),
            $this->createSessionAuthenticationStrategyMock(),
            $this->createHttpUtilsStub($checkRequestPathReturn = true),
            'providerKey',
            $this->createAuthenticationSuccessHandlerMock(),
            $this->createAuthenticationFailureHandlerMock(),
            $options = array('optional_attributes' => $expectedOptionalAttributes)
        );

        $listener->setRelyingParty($relyingPartyMock);

        $listener->handle($eventMock);

        $this->assertSame(
            $expectedOptionalAttributes,
            $duplicatedRequestMock->attributes->get('optional_attributes')
        );
    }

    /**
     * @test
     */
    public function shouldSetRelyingPartyRedirectResponseToEvent()
    {
        $requestMock = $this->createRequestStub(
            $hasSessionReturn = true,
            $hasPreviousSessionReturn = true,
            $duplicatedRequestMock = $this->createRequestMock()
        );

        $manageReturnRedirectResponse = new RedirectResponse('http://example.com/openid-provider');

        $relyingPartyMock = $this->createRelyingPartyStub($supportsReturn = true, $manageReturnRedirectResponse);

        $eventMock = $this->createGetResponseEventStub($requestMock);
        $eventMock
            ->expects($this->once())
            ->method('setResponse')
            ->with($manageReturnRedirectResponse)
        ;

        $listener = new OpenIdAuthenticationListener(
            $this->createTokenStorageMock(),
            $this->createAuthenticationManagerMock(),
            $this->createSessionAuthenticationStrategyMock(),
            $this->createHttpUtilsStub($checkRequestPathReturn = true),
            'providerKey',
            $this->createAuthenticationSuccessHandlerMock(),
            $this->createAuthenticationFailureHandlerMock(),
            $options = array()
        );

        $listener->setRelyingParty($relyingPartyMock);

        $listener->handle($eventMock);
    }

    /**
     * @test
     *
     * @expectedException RuntimeException
     * @expectedExceptionMessage must either return a RedirectResponse or instance of IdentityProviderResponse.
     */
    public function throwIfRelyingPartyReturnNeitherRedirectResponseOrIdentityProviderResponse()
    {
        $requestMock = $this->createRequestStub(
            $hasSessionReturn = true,
            $hasPreviousSessionReturn = true,
            $duplicateReturn = $this->createRequestMock()
        );

        $relyingPartyMock = $this->createRelyingPartyStub($supportsReturn = true, $manageReturn = 'invalid-return-value');

        $eventMock = $this->createGetResponseEventStub($requestMock);

        $listener = new OpenIdAuthenticationListener(
            $this->createTokenStorageMock(),
            $this->createAuthenticationManagerMock(),
            $this->createSessionAuthenticationStrategyMock(),
            $this->createHttpUtilsStub($checkRequestPathReturn = true),
            'providerKey',
            $this->createAuthenticationSuccessHandlerMock(),
            $this->createAuthenticationFailureHandlerMock(),
            $options = array()
        );

        $listener->setRelyingParty($relyingPartyMock);

        $listener->handle($eventMock);
    }

    /**
     * @test
     */
    public function shouldAddTokenToEachThrownAuthenticationException()
    {
        $expectedIdentityProviderResponse = new IdentityProviderResponse('an_identity');
        $expectedAuthenticationException = new AuthenticationException('an error');

        $requestMock = $this->createRequestStub(
            $hasSessionReturn = true,
            $hasPreviousSessionReturn = true,
            $duplicateReturn = $this->createRequestMock()
        );

        $relyingPartyMock = $this->createRelyingPartyStub(
            $supportsReturn = true,
            $manageReturn = $expectedIdentityProviderResponse
        );

        $authenticationManagerMock = $this->createAuthenticationManagerMock();
        $authenticationManagerMock
            ->expects($this->once())
            ->method('authenticate')
            ->will($this->throwException($expectedAuthenticationException))
        ;

        $testcase = $this;
        $authenticationFailureHandlerMock = $this->createAuthenticationFailureHandlerMock();
        $authenticationFailureHandlerMock
            ->expects($this->once())
            ->method('onAuthenticationFailure')
            ->will($this->returnCallback(function($request, $exception) use($testcase, $expectedAuthenticationException, $expectedIdentityProviderResponse) {
                $testcase->assertSame($exception, $expectedAuthenticationException);
                $testcase->assertInstanceOf('Fp\OpenIdBundle\Security\Core\Authentication\Token\OpenIdToken', $exception->getToken());

                return new Response('');
            }))
        ;

        $eventMock = $this->createGetResponseEventStub($requestMock);

        $listener = new OpenIdAuthenticationListener(
            $this->createTokenStorageMock(),
            $authenticationManagerMock,
            $this->createSessionAuthenticationStrategyMock(),
            $this->createHttpUtilsStub($checkRequestPathReturn = true),
            'providerKey',
            $this->createAuthenticationSuccessHandlerMock(),
            $authenticationFailureHandlerMock,
            $options = array()
        );

        $listener->setRelyingParty($relyingPartyMock);

        $listener->handle($eventMock);
    }

    /**
     * @test
     */
    public function shouldCreateOpenIdTokenUsingIdentityProviderResponseAndPassItToAuthenticationManager()
    {
        $requestMock = $this->createRequestStub(
            $hasSessionReturn = true,
            $hasPreviousSessionReturn = true,
            $duplicateReturn = $this->createRequestMock(),
            $getSessionReturn = $this->createSessionMock()
        );

        $expectedIdentity = 'the_identity';
        $expectedAttributes = array(
            'foo' => 'foo',
            'bar' => 'bar'
        );

        $relyingPartyMock = $this->createRelyingPartyStub(
            $supportsReturn = true,
            $manageReturnIdentityProviderResponse = new IdentityProviderResponse($expectedIdentity, $expectedAttributes)
        );

        $httpUtilsStub = $this->createHttpUtilsStub(
            $checkRequestPathReturn = true,
            $createRedirectResponseReturn = new RedirectResponse('uri')
        );

        $testCase = $this;
        $authenticationManagerMock = $this->createAuthenticationManagerMock();
        $authenticationManagerMock
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->isInstanceOf('Fp\OpenIdBundle\Security\Core\Authentication\Token\OpenIdToken'))
            ->will($this->returnCallback(function($actualOpenIdToken) use ($testCase, $expectedIdentity, $expectedAttributes){
                $testCase->assertEquals($expectedIdentity, $actualOpenIdToken->getIdentity());
                $testCase->assertEquals($expectedAttributes, $actualOpenIdToken->getAttributes());

                return $actualOpenIdToken;
            }))
        ;

        $eventMock = $this->createGetResponseEventStub($requestMock);

        $listener = new OpenIdAuthenticationListener(
            $this->createTokenStorageMock(),
            $authenticationManagerMock,
            $this->createSessionAuthenticationStrategyMock(),
            $httpUtilsStub,
            'providerKey',
            $this->createAuthenticationSuccessHandlerStub(),
            $this->createAuthenticationFailureHandlerMock(),
            $options = array()
        );

        $listener->setRelyingParty($relyingPartyMock);

        $listener->handle($eventMock);
    }

    /**
     * @test
     */
    public function shouldAddOpenIdTokenToSecurityContextIfSuccessfullyAuthenticated()
    {
        $expectedToken = new OpenIdToken('a_provider_key', 'identity');

        $requestMock = $this->createRequestStub(
            $hasSessionReturn = true,
            $hasPreviousSessionReturn = true,
            $duplicateReturn = $this->createRequestMock(),
            $getSessionReturn = $this->createSessionMock()
        );

        $relyingPartyMock = $this->createRelyingPartyStub(
            $supportsReturn = true,
            $manageReturnIdentityProviderResponse = new IdentityProviderResponse('identity')
        );

        $httpUtilsStub = $this->createHttpUtilsStub(
            $checkRequestPathReturn = true,
            $createRedirectResponseReturn = new RedirectResponse('uri')
        );

        $authenticationManagerMock = $this->createAuthenticationManagerMock();
        $authenticationManagerMock
            ->expects($this->once())
            ->method('authenticate')
            ->will($this->returnValue($expectedToken))
        ;

        $securityContextMock = $this->createTokenStorageMock();
        $securityContextMock
            ->expects($this->once())
            ->method('setToken')
            ->with($expectedToken)
        ;

        $eventMock = $this->createGetResponseEventStub($requestMock);

        $listener = new OpenIdAuthenticationListener(
            $securityContextMock,
            $authenticationManagerMock,
            $this->createSessionAuthenticationStrategyMock(),
            $httpUtilsStub,
            'providerKey',
            $this->createAuthenticationSuccessHandlerStub(),
            $this->createAuthenticationFailureHandlerMock(),
            $options = array()
        );

        $listener->setRelyingParty($relyingPartyMock);

        $listener->handle($eventMock);
    }
 
    protected function createRelyingPartyMock()
    {
        return $this->createMock('Fp\OpenIdBundle\RelyingParty\RelyingPartyInterface');
    }

    protected function createRelyingPartyStub($supportsReturn = null, $manageReturn = null)
    {
        $relyingPartyMock = $this->createRelyingPartyMock();

        $relyingPartyMock
            ->expects($this->any())
            ->method('supports')
            ->will($this->returnValue($supportsReturn))
        ;
        $relyingPartyMock
            ->expects($this->any())
            ->method('manage')
            ->will($this->returnValue($manageReturn))
        ;

        return $relyingPartyMock;
    }

    protected function createRequestMock()
    {
        return $this->createMock('Symfony\Component\HttpFoundation\Request', array(), array(), '', false, false);
    }

    protected function createRequestStub($hasSessionReturn = null, $hasPreviousSession = null, $duplicateReturn = null, $getSessionReturn = null)
    {
        $requestMock = $this->createRequestMock();

        $requestMock
            ->expects($this->any())
            ->method('hasSession')
            ->will($this->returnValue($hasSessionReturn))
        ;
        $requestMock
            ->expects($this->any())
            ->method('hasPreviousSession')
            ->will($this->returnValue($hasPreviousSession))
        ;
        $requestMock
            ->expects($this->any())
            ->method('duplicate')
            ->will($this->returnValue($duplicateReturn))
        ;
        $requestMock
            ->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($getSessionReturn))
        ;

        return $requestMock;
    }

    protected function createSessionMock()
    {
        return $this->createMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
    }

    protected function createTokenStorageMock()
    {
        return $this->createMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
    }

    protected function createAuthenticationManagerMock()
    {
        return $this->createMock('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface');
    }

    protected function createSessionAuthenticationStrategyMock()
    {
        return $this->createMock('Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface');
    }

    protected function createHttpUtilsMock()
    {
        return $this->createMock('Symfony\Component\Security\Http\HttpUtils');
    }

    protected function createHttpUtilsStub($checkRequestPathResult = null, $createRedirectResponseReturn = null)
    {
        $httpUtilsMock = $this->createHttpUtilsMock();

        $httpUtilsMock
            ->expects($this->any())
            ->method('checkRequestPath')
            ->will($this->returnValue($checkRequestPathResult))
        ;
        $httpUtilsMock
            ->expects($this->any())
            ->method('createRedirectResponse')
            ->will($this->returnValue($createRedirectResponseReturn))
        ;

        return $httpUtilsMock;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface
     */
    protected function createAuthenticationFailureHandlerMock()
    {
        return $this->createMock('Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface');
    }

    protected function createGetResponseEventMock()
    {
        return $this->createMock('Symfony\Component\HttpKernel\Event\GetResponseEvent', array(), array(), '', false);
    }

    protected function createGetResponseEventStub($request = null)
    {
        $getResponseEventMock = $this->createGetResponseEventMock();

        $getResponseEventMock
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        return $getResponseEventMock;
    }

    protected function createAuthenticationSuccessHandlerMock()
    {
        return $this->createMock('Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface');
    }

    protected function createAuthenticationSuccessHandlerStub()
    {
        $handlerMock = $this->createAuthenticationSuccessHandlerMock();
        
        $handlerMock
            ->expects($this->any())
            ->method('onAuthenticationSuccess')
            ->will($this->returnValue(new Response()))
        ;
        
        return $handlerMock;
    }
}