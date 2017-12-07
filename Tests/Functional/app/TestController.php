<?php
namespace Fp\OpenIdBundle\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * @author Kotlyar Maksim <kotlyar.maksim@gmail.com>
 * @since 4/27/12
 */
class TestController
{
    use ContainerAwareTrait;

    public function securedAction()
    {
        return new Response('Secured Content');
    }
}
