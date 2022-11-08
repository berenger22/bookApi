<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BookController extends AbstractController
{
    #[Route('/api/books', name: 'app_book', methods:['GET'])]
    public function getAllBooks(BookRepository $bookRepo, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page',1);
        $limit = $request->get('limit', 3);

        $idCache = 'getAllBooks-'.$page.'-'.$limit;
        $booksJsonList = $cache->get($idCache, function (ItemInterface $item) use ($bookRepo, $page, $limit, $serializer){
            echo "cache\n";
            $item->tag("booksCache");
            //Permet de préciser la durer du cache (60 secondes)
            // $item->expiresAfter(60);
            $bookList = $bookRepo->findAllByPaganitation($page, $limit);
            $context = SerializationContext::create()->setGroups(["getBooks"]);
            return $serializer->serialize($bookList, "json", $context);
        });
        return new JsonResponse($booksJsonList, Response::HTTP_OK,[],true);
    }

    #[Route('/api/books/{id}', name:'detail_book', methods:['GET'])]
    public function getDetailBook(Book $book, SerializerInterface $serializer) :JsonResponse
    {
        $context = SerializationContext::create()->setGroups(["getBooks"]);
        $booksJson = $serializer->serialize($book, "json", $context);
        return new JsonResponse($booksJson, Response::HTTP_OK,[],true);
    }

    #[Route('/api/books', name:'create_book', methods:['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    public function createBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGeneratorInterface, AuthorRepository $authorRepo, ValidatorInterface $validator, TagAwareCacheInterface $cache) :JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(),Book::class, 'json');

        $erreurs = $validator->validate($book);
        if($erreurs->count() > 0){
            return new JsonResponse($serializer->serialize($erreurs, "json"), Response::HTTP_BAD_REQUEST,[], true);
            //throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, "La requête est invalide");
        }
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $book->setAuthor($authorRepo->find($idAuthor));
        $em->persist($book);
        $em->flush();

        $context = SerializationContext::create()->setGroups(["getBooks"]);
        $bookJson = $serializer->serialize($book,"json",$context);
        $location = $urlGeneratorInterface->generate('detail_book',["id" => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        // On vide le cache.
        $cache->invalidateTags(["booksCache"]);

        return new JsonResponse($bookJson, Response::HTTP_CREATED,["location" => $location], true);
    }

    #[Route('/api/books/{id}', name:'update_book', methods:['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier un livre')]
    public function updateBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, Book $currentBook, AuthorRepository $authorRepo, ValidatorInterface $validator, TagAwareCacheInterface $cache) :JsonResponse
    {
        $newBook = $serializer->deserialize($request->getContent(),Book::class, 'json');
        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());

        $erreurs = $validator->validate($currentBook);
        if($erreurs->count() > 0){
            return new JsonResponse($serializer->serialize($erreurs, "json"), Response::HTTP_BAD_REQUEST,[], true);
        }
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $currentBook->setAuthor($authorRepo->find($idAuthor));
        $em->persist($currentBook);
        $em->flush();

        // On vide le cache.
        $cache->invalidateTags(["booksCache"]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/books/{id}', name:'delete_book', methods:['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un livre')]
    public function getDeleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cache) :JsonResponse
    {
        $cache->invalidateTags(["booksCache"]);
        $em->remove($book);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
