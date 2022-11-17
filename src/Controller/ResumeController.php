<?php

namespace App\Controller;

use App\Entity\Operation;
use App\Entity\Resume;
use App\Form\ResumeType;
use App\Repository\CompanyRepository;
use App\Repository\OperationRepository;
use App\Repository\ResumeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ResumeController extends AbstractController
{
    #[Route('/resume', name: 'app_resume_list')]
    public function index(ResumeRepository $resumeRepository): Response
    {
        $resume = $resumeRepository->findBy(['deleted' => null], ['created_at' => 'DESC']);
        return $this->render('resume/index.html.twig', [
            'allresume' => $resume,
        ]);
    }

    #[Route('/my/resume', name: 'app_resume_my')]
    public function myResume(
        Request $request,
        EntityManagerInterface $em,
        ResumeRepository $resumeRepository,
        SluggerInterface $slugger
    ): Response {
        $oldResume = $resumeRepository->findOneBy(['user' => $this->getUser(), 'deleted' => null]);

        $form = $this->createForm(
            ResumeType::class,
            $oldResume
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /**
             * @var Resume $resume
             */
            $resume = $form->getData();

            /**
             * @var UploadedFile|null $file
             */
            if ($file = $form->get('file')->getData()) {
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $filename = $slugger
                    ->slug($originalName)
                    ->append('_' . uniqid())
                    ->append('.' . $file->guessExtension())
                    ->toString();
                $uploadDir = $this->getParameter('document_uploads_dir');
                $file->move($uploadDir, $filename);

                $resume->setFile($filename);
            }

            if ($oldResume) {
                $resume->setUpdatedAt(\date_create_immutable());
            } else {
                $resume->setCreatedAt(\date_create_immutable());
            }

            $resume->setUser($this->getUser());

            $em->persist($resume);
            $em->flush();

            return $this->redirectToRoute('app_resume_list');
        }

        return $this->render('resume/my.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/stats/resume/', name: 'app_resume_stats')]
    public function resumeStats(ResumeRepository $resumeRepository): Response
    {
        $resume = $resumeRepository->findBy(['deleted' => null], ['likeCount' => 'DESC']);
        return $this->render('resume/stats.html.twig', [
            'allresume' => $resume,
        ]);
    }

    #[Route('/my/resume/{id}', name: 'app_resume_delete')]
    public function delete(Resume $resume, EntityManagerInterface $em): Response
    {
        if ($resume->getUser() == $this->getUser()) {
            $resume->setDeleted(1);
            $resume->setUpdatedAt(\date_create_immutable());
            $em->flush();
        }

        return $this->redirectToRoute('app_resume_my');
    }

    #[Route('/resume/{type<like|dislike>}/{id}', name: 'app_resume_like')]
    public function like(
        OperationRepository $operationRepository,
        ResumeRepository $resumeRepository,
        CompanyRepository $companyRepository,
        EntityManagerInterface $em,
        string $type,
        int $id
    ): Response {
        if ($this->isGranted('ROLE_COMPANY')) {
            if (!$company = $companyRepository->findOneBy(['user' => $this->getUser(), 'deleted' => null])) {
                return $this->redirectToRoute('app_company_my');
            }

            if ($resume = $resumeRepository->find($id)) {
                if (!$operationRepository->createQueryBuilder('o')
                    ->where('o.initiator = :initiator')
                    ->andWhere('o.resume = :resume')
                    ->andWhere('o.type = :type1')
                    ->orWhere('o.type = :type2')
                    ->setParameter('initiator', $this->getUser()->getId())
                    ->setParameter('resume', $id)
                    ->setParameter('type1', 'like')
                    ->setParameter('type2', 'dislike')
                    ->getQuery()
                    ->getResult()) {
                    $operation = new Operation();
                    $operation
                        ->setInitiator($this->getUser()->getId())
                        ->setResume($resume)
                        ->setCompany($company)
                        ->setType($type)
                        ->setCreatedAt(\date_create_immutable());
                    if ($type == 'like') {
                        $resume->like();
                    } else {
                        $resume->dislike();
                    }
                    $em->persist($resume);
                    $em->persist($operation);
                    $em->flush();
                } else {
                    throw new \Exception('Не, уже взаимодействовал');
                }
            }
        }
        return $this->redirectToRoute('app_resume_list');
    }
}
