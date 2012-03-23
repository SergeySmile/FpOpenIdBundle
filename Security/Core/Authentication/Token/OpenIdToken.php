<?php
namespace Fp\OpenIdBundle\Security\Core\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\HttpFoundation\Response;

class OpenIdToken extends AbstractToken
{
    /**
     * @var string
     */
    protected $identity;

    /**
     * @param string
     * @param array $attributes
     * @param array $roles
     */
    public function __construct($identity, array $roles = array())
    {
        parent::__construct($roles);
        parent::setAuthenticated(count($this->getRoles()) > 0);

        $this->identity = $identity;
    }

    /**
     * @return string
     */
    public function getIdentity()
    {
        return $this->identity;
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthenticated($isAuthenticated)
    {
        if ($isAuthenticated) {
            throw new \LogicException('Cannot set this token to trusted after instantiation.');
        }

        parent::setAuthenticated(false);
    }

    /**
     * @return void
     */
    public function getCredentials()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array($this->identity, parent::serialize()));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($str)
    {
        list($this->identity, $parentStr) = unserialize($str);

        parent::unserialize($parentStr);
    }
}