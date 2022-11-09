<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        //Création d'un utilisateur
        $user = new User();
        $user->setEmail("user@bookEmail.fr");
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "test"));
        $manager->persist($user);

        //Création d'un administrateur
        $admin = new User();
        $admin->setEmail("admin@bookEmail.fr");
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->userPasswordHasher->hashPassword($admin, "test"));
        $manager->persist($admin);

        //Création liste d'auteurs
        $ListAuthor = [];
        $faker = \Faker\Factory::create('fr_FR');
        for ($i=0; $i < 10 ; $i++) { 
            $author = new Author();
            $author->setLastName($faker->lastname());
            $author->setFirstName($faker->firstname());
            $manager->persist($author);
            $ListAuthor[]=$author;
        }

        //Création liste de livres
        for ($i=0; $i <20 ; $i++) { 
            $book = new Book();
            $book->setTitle($faker->sentence(3));
            $book->setCoverText($faker->text(100));
            $book->setComment($faker->text(250));
            $book->setAuthor($ListAuthor[array_rand($ListAuthor)]);
            $manager->persist($book);
        }

        $manager->flush();
    }
}
