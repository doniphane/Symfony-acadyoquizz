<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\JwtCookieService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Contrôleur pour l'authentification API avec cookies JWT
 * 
 * Ce contrôleur :
 * 1. Gère la connexion avec génération de cookie HttpOnly
 * 2. Gère la déconnexion avec suppression du cookie
 * 3. Fournit les informations de l'utilisateur connecté
 */
#[Route('/api')]
class ApiAuthController extends AbstractController
{
    public function __construct(
        private JwtCookieService $jwtCookieService
    ) {
    }

    /**
     * Point d'entrée pour la connexion
     * Cette route est gérée par Lexik JWT, on ajoute juste le cookie
     */
    #[Route('/login_check', name: 'api_login_check', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // Cette méthode ne sera jamais appelée directement
        // Lexik JWT gère l'authentification via json_login
        throw new \Exception('This endpoint is handled by Lexik JWT');
    }

    /**
     * Point d'entrée pour la déconnexion
     * Supprime le cookie d'authentification
     */
    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $response = new JsonResponse([
            'message' => 'Déconnexion réussie'
        ]);

        // Supprimer le cookie d'authentification
        $this->jwtCookieService->removeCookieFromResponse($response);

        return $response;
    }




}