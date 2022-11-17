<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Operation;
use App\Form\CompanyType;
use App\Repository\CompanyRepository;
use App\Repository\OperationRepository;
use App\Repository\ResumeRepository;
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


    #[Route('/company/{id}', name: 'app_company_invite')]
    public function invite(
        CompanyRepository $companyRepository,
        ResumeRepository $resumeRepository,
        EntityManagerInterface $em,
        OperationRepository $operationRepository,
        int $id
    ): Response {
        if ($this->isGranted('ROLE_WORKER')) {
            if (!$resume = $resumeRepository->findOneBy(['user' => $this->getUser(), 'deleted' => null])) {
                return $this->redirectToRoute('app_resume_my');
            }
            /**
             * @var Company $company
             */
            if ($company = $companyRepository->find($id)) {
                if ($company->isDeleted() == null) {
                    if (!$operationRepository->findOneBy(
                        ['initiator' => $this->getUser(), 'company' => $company, 'type' => 'invite']
                    )) {
                        $operation = new Operation();
                        $operation->setCompany($company);
                        $operation->setResume($resume);
                        $operation->setType('invite');
                        $operation->setInitiator($this->getUser()->getId());
                        $operation->setCreatedAt(\date_create_immutable());
                        $em->persist($operation);
                        $em->flush();
                    } else {
                        throw new \Exception('Не, уже отправлял');
                    }
                }
            }
        }

        return $this->redirectToRoute('app_company_list');
    }
}
