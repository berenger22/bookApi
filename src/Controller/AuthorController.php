<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;

class AuthorController extends AbstractController
{
    #[Route('/api/authors', name: 'app_author', methods:["GET"])]
    public function getAllAuthor(AuthorRepository $authorRepo, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page');
        $limit = $request->get('limit');
        $idCache = "getAllAuthor-".$page."-".$limit;
        $authorJsonList = $cache->get($idCache, function (ItemInterface $item) use ($authorRepo, $page, $limit, $serializer){
            echo "cache\n";
            $item->tag("authorsCache");
            $author = $authorRepo->findAllWithPagination($page,$limit);
            $context = SerializationContext::create()->setGroups(["getAuthors"]);
            return $serializer->serialize($author,"json",$context);
        });
        return new JsonResponse($authorJsonList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/authors/{id}', name:'detailAuthor', methods:['GET'])]
    public function getDetailAuthor(Author $author, SerializerInterface $serializer) :JsonResponse
    {
        $context = SerializationContext::create()->setGroups(["getAuthors"]);
        $authorJson = $serializer->serialize($author,"json",$context);
        return new JsonResponse($authorJson, Response::HTTP_OK, [], true);
    }

    #[Route('/api/authors', name:'createAuthors', methods:["POST"])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour rajouter un auteur')]
    public function createAuthors(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGeneratorInterface, ValidatorInterface $validator, TagAwareCacheInterface $cache) :JsonResponse
    {
        $author = $serializer->deserialize($request->getContent(),Author::class,'json');
        $erreurs = $validator->validate($author);
        if($erreurs->count() > 0){
            return new JsonResponse($serializer->serialize($erreurs, "json"), Response::HTTP_BAD_REQUEST,[], true);
            //throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, "La requête est invalide");
        }
        $em->persist($author);
        $em->flush();

        $context = SerializationContext::create()->setGroups(["getAuthors"]);
        $authorJson = $serializer->serialize($author,'json',$context);
        $location = $urlGeneratorInterface->generate('detailAuthor',["id" => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $cache->invalidateTags(["authorsCache"]);
        return new JsonResponse($authorJson, Response::HTTP_CREATED,["location" => $location], true);
    }

    #[Route('/api/authors/{id}', name:'update_authors', methods:["PUT"])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier un auteur')]
    public function updateAuthors(Request $request,Author $currentAuthor, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache) :JsonResponse
    {
        $newAuthor = $serializer->deserialize($request->getContent(),Author::class, 'json');
        $currentAuthor->setLastName($newAuthor->getLastName());
        $currentAuthor->setFirstName($newAuthor->getFirstName());

        $erreurs = $validator->validate($currentAuthor);
        if($erreurs->count() > 0){
            return new JsonResponse($serializer->serialize($erreurs, "json"), Response::HTTP_BAD_REQUEST,[], true);
            //throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, "La requête est invalide");
        }
        $em->persist($currentAuthor);
        $em->flush();

        $cache->invalidateTags(["authorsCache"]);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/authors/{id}', name:'delete_author', methods:["DELETE"])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un auteur')]
    public function deleteAuthor(Author $author, EntityManagerInterface $em, TagAwareCacheInterface $cache) : JsonResponse
    {
        $cache->invalidateTags(["authorsCache"]);
        $em->remove($author);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

}
