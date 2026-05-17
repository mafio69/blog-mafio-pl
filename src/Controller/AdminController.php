<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ProjectStateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    #[Route('/project-state', name: 'admin_project_state')]
    public function projectState(ProjectStateService $projectState): Response
    {
        return $this->render('admin/project_state.html.twig', [
            'sections' => $projectState->getAllGroupedBySections(),
        ]);
    }
}
