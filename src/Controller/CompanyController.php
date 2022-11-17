<?php

namespace App\Controller;

use App\Entity\Company;
use App\Form\CompanyType;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompanyController extends AbstractController
{
    #[Route('/company', name: 'app_company_list')]
    public function index(CompanyRepository $companyRepository): Response
    {
        $company = $companyRepository->findBy(['deleted' => null], ['created_at' => 'DESC']);
        return $this->render('company/index.html.twig', [
            'allcompany' => $company,
        ]);
    }

    #[Route('/my/company', name: 'app_company_my')]
    public function myCompany(
        Request $request,
        EntityManagerInterface $em,
        CompanyRepository $companyRepository
    ): Response {
        $oldCompany = $companyRepository->findOneBy(['user' => $this->getUser(), 'deleted' => null]);
        $form = $this->createForm(
            CompanyType::class,
            $oldCompany
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /**
             * @var Company $company
             */
            $company = $form->getData();

            if ($oldCompany) {
                $company->setUpdatedAt(\date_create_immutable());
            } else {
                $company->setCreatedAt(\date_create_immutable());
            }

            $company->setUser($this->getUser());

            $em->persist($company);
            $em->flush();

            return $this->redirectToRoute('app_company_list');
        }

        return $this->render('company/my.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/my/company/{id}', name: 'app_company_delete')]
    public function delete(Company $company, EntityManagerInterface $em): Response
    {
        if ($company->getUser() == $this->getUser()) {
            $company->setDeleted(1);
            $company->setUpdatedAt(\date_create_immutable());
            $em->flush();
        }

        return $this->redirectToRoute('app_company_my');
    }
}
