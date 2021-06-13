<?php

namespace App\Controller\Front;

use App\Repository\PropertyRepository;
use App\Repository\SearchRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Property;
use App\Form\PropertyType;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @Route("/properties")
 */
class PropertyController extends AbstractController
{
    /**
     * @Route("/", name="property_index", methods={"GET"})
     */
    public function index(PropertyRepository $propertyRepository, TokenStorageInterface $tokenStorage): Response
    {
        // Utilisateur courant
        $user = $tokenStorage->getToken()->getUser();

        return $this->render('front/property/index.html.twig', [
            'properties' => $propertyRepository->getUserProperties($user)
        ]);
    }

    /**
     * @Route("/new", name="property_new", methods={"GET","POST"})
     * @param Request $request
     * @param SearchRepository $searchRepository
     * @return Response
     */
    public function new(Request $request, SearchRepository $searchRepository): Response
    {
        $property = new Property();
        $property->setOwner($this->getUser());
        $form = $this->createForm(PropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($property);
            $entityManager->flush();

            // TODO : lancer le recherche lorsqu'un nouveau bien est créé.
            $searchRepository->findInterestedUsers($property);
            dd($searchRepository);

            // TODO : Faire une Queue pour envoyer les mails de façon asynchrone aux utilisateurs.

            return $this->redirectToRoute('front_property_index');
        }

        return $this->render('front/property/new.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="property_show", methods={"GET"})
     * @param Property $property
     * @return Response
     */
    public function show(Property $property): Response
    {
        return $this->render('front/property/show.html.twig', [
            'property' => $property,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="property_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Property $property): Response
    {
        $form = $this->createForm(PropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('front_property_index');
        }

        return $this->render('front/property/edit.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="property_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Property $property): Response
    {
        if ($this->isCsrfTokenValid('delete' . $property->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($property);
            $entityManager->flush();
        }

        return $this->redirectToRoute('front_property_index');
    }
}
