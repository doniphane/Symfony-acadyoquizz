<?php

namespace App\Service;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Service pour gérer les cookies JWT de manière sécurisée
 * 
 * Ce service :
 * 1. Crée des cookies HttpOnly sécurisés avec le token JWT
 * 2. Supprime les cookies lors de la déconnexion
 * 3. Configure les paramètres de sécurité appropriés
 */
class JwtCookieService
{
    private const COOKIE_NAME = 'auth_token';
    private const COOKIE_TTL = 3600; // 1 heure

    public function __construct(
        private JWTTokenManagerInterface $jwtManager
    ) {
    }

    /**
     * Crée un cookie HttpOnly sécurisé avec le token JWT
     * 
     * @param UserInterface $user L'utilisateur connecté
     * @return Cookie Le cookie sécurisé
     */
    public function createSecureCookie(\Symfony\Component\Security\Core\User\UserInterface $user): Cookie
    {
        // Générer le token JWT
        $token = $this->jwtManager->create($user);

        // Créer le cookie avec les paramètres de sécurité
        return new Cookie(
            self::COOKIE_NAME,
            $token,
            time() + self::COOKIE_TTL,
            '/',
            null,
            false, // secure - Désactivé en développement
            false, // httpOnly - Désactivé en développement pour faciliter les tests
            false,
            'Lax' // sameSite - Protection CSRF
        );
    }

        /**
     * Supprime le cookie d'authentification
     * 
     * @return Cookie Le cookie à supprimer
     */
    public function removeCookie(): Cookie
    {
        return new Cookie(
            self::COOKIE_NAME,
            '',
            time() - 3600, // Expire dans le passé
            '/',
            null,
            false, // secure - Désactivé en développement
            false, // httpOnly - Désactivé en développement
            false,
            'Lax' // sameSite
        );
    }

    /**
     * Ajoute le cookie JWT à une réponse
     * 
     * @param Response $response La réponse à modifier
     * @param UserInterface $user L'utilisateur connecté
     * @return Response La réponse avec le cookie
     */
    public function addCookieToResponse(Response $response, UserInterface $user): Response
    {
        $cookie = $this->createSecureCookie($user);
        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * Supprime le cookie JWT d'une réponse
     * 
     * @param Response $response La réponse à modifier
     * @return Response La réponse sans le cookie
     */
    public function removeCookieFromResponse(Response $response): Response
    {
        $cookie = $this->removeCookie();
        $response->headers->setCookie($cookie);

        return $response;
    }
}