<?php declare(strict_types=1);

namespace Shopwell\Core\Installer\Controller;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Installer\Requirements\RequirementsValidatorInterface;
use Shopwell\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
class RequirementsController extends InstallerController
{
    /**
     * @param iterable|RequirementsValidatorInterface[] $validators
     */
    public function __construct(private readonly iterable $validators)
    {
    }

    #[Route(path: '/installer/requirements', name: 'installer.requirements', methods: ['GET', 'POST'])]
    public function requirements(Request $request): Response
    {
        $checks = new RequirementsCheckCollection();

        foreach ($this->validators as $validator) {
            $checks = $validator->validateRequirements($checks);
        }

        if ($request->isMethod(Request::METHOD_POST) && !$checks->hasError()) {
            return $this->redirectToRoute('installer.license');
        }

        return $this->renderInstaller(
            '@Installer/installer/requirements.html.twig',
            [
                'requirementChecks' => $checks,
                'noWayBack' => $request->getSession()->has('extendSteps'),
            ]
        );
    }
}
