<?php

namespace App\Security;

use App\Entity\ProfilePicture;
use App\Entity\User;
use App\Service\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Security\Authenticator\SocialAuthenticator;
use KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Core\Security;

class GoogleAuthenticator extends SocialAuthenticator
{
    use TargetPathTrait;
    /**
     * @var ClientRegistry
     */
    private $clientRegistry;

    /**
     * @var EntityManagerInterface
     */
    private $em;
    private $urlGenerator;
    /**
     * @var Mailer
     */
    private $mailer;


    /**
     * GoogleAuthenticator constructor.
     * @param ClientRegistry $clientRegistry
     * @param EntityManagerInterface $em
     */
    public function __construct(
        Mailer $mailer,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $em,
        RouterInterface $router,
        $projectDirectory,
        $userPictureDirectory
    ) {
        $this->mailer = $mailer;
        $this->clientRegistry = $clientRegistry;
        $this->em = $em;
        $this->router = $router;
        $this->projectDirectory = $projectDirectory;
        $this->userPictureDirectory = $userPictureDirectory;
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function supports(Request $request)
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    /**
     * @param Request $request
     * @return \League\OAuth2\Client\Token\AccessToken|mixed
     */
    public function getCredentials(Request $request)
    {
        // this method is only called if supports() returns true

        return $this->fetchAccessToken($this->getGoogleClient());
    }

    /**
     * @param mixed $credentials
     * @param UserProviderInterface $userProvider
     * @return User|null|object|\Symfony\Component\Security\Core\User\UserInterface
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        /** @var GoogleUser $googleUser */
        $googleUser = $this->getGoogleClient()
            ->fetchUserFromToken($credentials);

        $email = $googleUser->getEmail();

        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new User();

            // Store distant avatar to local and register it in user
            $distantAvatarContent = file_get_contents($googleUser->getAvatar());
            $localFileName = preg_replace('/https?:\/\/lh3.googleusercontent.com\/a-\/(.{1,})/i', '$1', $googleUser->getAvatar()) . '.png';
            $localAvatarPath = $this->projectDirectory . '/public' . $this->userPictureDirectory . '/' . $localFileName;

            //if (file_put_contents($localAvatarPath, $distantAvatarContent) === false) {
                //throw new RuntimeException('Le fichier a mal été copié');
            //}

            /** @var ProfilePicture $avatar The user avatar object */
            $avatar = new ProfilePicture();
            $avatar->setImageName($localFileName);

            $user->setEnabled(1);
            $user->setEmail($googleUser->getEmail());
            $user->setRoles(["ROLE_USER"]);
            $user->setPassword("");
            $user->setFirstname($googleUser->getLastName());
            $user->setLastname($googleUser->getFirstName());
            $user->setCivility("");
            $user->setProfilePicture($avatar);
            $this->em->persist($user);
            $this->em->persist($avatar);
            $this->em->flush();

            $this->mailer->sendEmailWelcome($user->getEmail(), $user->getToken(), $user->getFirstname() . ' ' . $user->getLastname());
        }
        return $user;
    }

    /**
     * @return GoogleClient
     */
    private function getGoogleClient()
    {
        return $this->clientRegistry->getClient('google');
    }


    /**
     * Called when authentication is needed, but it's not sent.
     * This redirects to the 'login'.
     *
     * @param Request $request
     * @param AuthenticationException|null $authException
     *
     * @return RedirectResponse
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new RedirectResponse('/login');
    }


    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        if ($request->hasSession()) {
            $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        }

        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey)
    {
        $targetPath = $this->getTargetPath($request->getSession(), $providerKey);
        return new RedirectResponse($targetPath ?: '/google/inscription');
    }
}
