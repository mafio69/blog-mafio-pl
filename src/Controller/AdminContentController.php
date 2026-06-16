<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ContentGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/content')]
class AdminContentController extends AbstractController
{
    private const SECTIONS = [
        'ai_tech' => [
            'Modele & Badania AI',
            'AI w Praktyce',
            'Biznes & Rynek',
            'Hardware & Infrastruktura',
        ],
        'php_dev' => [
            'PHP & Backend',
            'JavaScript & Frontend',
            'IDE & Narzędzia',
            'Programowanie ogólnie',
        ],
        'none' => [],
    ];

    // -------------------------------------------------------------------------
    // GET /admin/content — formularz generatora
    // -------------------------------------------------------------------------

    #[Route('', name: 'admin_content_generator', methods: ['GET'])]
    public function generator(): Response
    {
        return $this->render('admin/content/generator.html.twig', [
            'sections' => self::SECTIONS,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /admin/content/generate — artykuł lub felieton
    // -------------------------------------------------------------------------

    #[Route('/generate', name: 'admin_content_generate', methods: ['POST'])]
    public function generate(Request $request, ContentGeneratorService $generator): JsonResponse
    {
        if (!$this->isCsrfTokenValid('content_generate', $request->request->get('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $mode           = $request->request->get('mode', 'article');
        $prompt         = trim((string) $request->request->get('prompt', ''));
        $newsletterType = $request->request->get('newsletter_type', 'none');
        $section        = $request->request->get('section', '');
        $tone           = $request->request->get('tone', 'opinionated');
        $length         = $request->request->get('length', 'medium');
        $tag            = strtoupper(trim((string) $request->request->get('tag', '')));
        $isLead         = (bool) $request->request->get('is_lead', false);

        if (empty($prompt)) {
            return $this->json(['error' => 'Prompt jest wymagany'], 400);
        }

        $params = compact('prompt', 'newsletterType', 'section', 'tone', 'length', 'tag', 'isLead');
        // Klucze muszą pasować do service (snake_case)
        $params['newsletter_type'] = $params['newsletterType'];
        $params['is_lead']         = $params['isLead'];

        try {
            $result = match ($mode) {
                'felieton' => $generator->generateFelieton($params),
                default    => $generator->generateArticle($params),
            };

            return $this->json([
                'ok'     => true,
                'mode'   => $mode,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /admin/content/viral-research — viral research na temat
    // -------------------------------------------------------------------------

    #[Route('/viral-research', name: 'admin_content_viral_research', methods: ['POST'])]
    public function viralResearch(Request $request, ContentGeneratorService $generator): JsonResponse
    {
        if (!$this->isCsrfTokenValid('content_viral', $request->request->get('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $topic = trim((string) $request->request->get('prompt', ''));

        if (empty($topic)) {
            return $this->json(['error' => 'Temat jest wymagany'], 400);
        }

        try {
            $result = $generator->researchVirals($topic);

            return $this->json([
                'ok'          => true,
                'candidates'  => $result['candidates'],
                'saved_count' => $result['saved_count'],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /admin/content/sections — helper AJAX do aktualizacji selecta sekcji
    // -------------------------------------------------------------------------

    #[Route('/sections', name: 'admin_content_sections', methods: ['GET'])]
    public function sections(Request $request): JsonResponse
    {
        $type = $request->query->get('type', 'none');
        return $this->json(self::SECTIONS[$type] ?? []);
    }
}
