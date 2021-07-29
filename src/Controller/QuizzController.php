<?php

namespace App\Controller;

use App\Entity\Quizz;
use App\Entity\Theme;
use App\Form\QuizzType;
use App\Entity\Historic;
use App\Entity\Question;
use App\Form\AnswerType;
use App\Form\PropositionType;
use App\Repository\QuizzRepository;
use App\Repository\ThemeRepository;
use App\Service\SessionQuizzService;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PropositionRepository;
use App\Service\MessageGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;



/**
 * @Route("/quizz", name="quizz_")
 */
class QuizzController extends AbstractController
{
	/**
	 * @Route("/list", name="list")
	 */
	public function index(QuizzRepository $quizzRepository): Response
	{
		

		return $this->render('quizz/list.html.twig', [
			'quizz' => $quizzRepository->findAllNotRandom(),
		]);
	}

	/**
	 * @Route("/list/{id}", name="themeQuizz")
	 */
	public function listThemeId(Theme $theme): Response
	{      

		return $this->render('quizz/list.html.twig', [
			'quizz' => $theme->getQuizz() ,
		]);
	}

	/**
	 * @Route("/{id}", name="show", requirements={"id"="\d+"})
	 *
	 * @return Response
	 */
	public function show(int $id, Quizz $quizz=null,  RequestStack $requestStack, PropositionRepository $proposition, SessionQuizzService $sessionQuizz, MessageGenerator $messageGenerator, QuizzRepository $quizzRepository)
	{
		// We want to display a 404 if the quizz doesn't exist.
		// We order a quizz by its id
		// If quizz existes we can play 
		//else we receive a 404
		if ($quizz === null) {
			$this->addFlash('', $messageGenerator->randomErrorMessage());
			return $this->render('errors/error404.html.twig');
		}
		
		$sessionQuizz->start();

		$questions = $quizz->getQuestions();

		// Verify if form is Sent
		if (isset($_POST["options"])) {

			$reponse = $proposition->find($_POST["options"]);

			// Call NoCheat and Show the same last question if error
			if ($sessionQuizz->NoCheat() === false) {
				return $this->render('quizz/show.html.twig', [
					'quizz' => $quizz,
					'question' => $questions[$sessionQuizz->getQuestionNumber()],
				]);
			}

			// Verify if answer is valid
			if ($reponse->getIsValid() == true) {
				$sessionQuizz->addPoint();
			}

			// Redirect to result if quizz Ended
			if ($sessionQuizz->endQuizz() === true) {
				//dd('redirect');
				return $this->redirectToRoute('quizz_result', ['id' => $quizz->getId()]);
			}

			$sessionQuizz->nextQuestion();
		}

		return $this->render('quizz/show.html.twig', [
			'quizz' => $quizz,
			'question' => $questions[$sessionQuizz->getQuestionNumber()],
		]);
	}


	/**
	 * @Route("/{id}/result", name="result")
	 */
	public function result(Quizz $quizz, RequestStack $requestStack, SessionQuizzService $sessionQuizz)
	{

		$sessionQuizz->start();

		// Redirect to quizz if quizz not Ended
		if ($sessionQuizz->endQuizz() === false) {
			return $this->redirectToRoute('quizz_show', ['id' => $quizz->getId()]);
		}

		// Add Score, User and Quizz in Historic entity
		$historic = new Historic;
		$historic->setUser($this->getUser());
		$historic->setQuizz($quizz);
		$historic->setScore($sessionQuizz->scoreQuizz());
		$historic->setOutOf(count($quizz->getQuestions()));

		$em = $this->getDoctrine()->getManager();
		$em->persist($historic);

		// If user is connected : save into BDD and delete session's quizz
		if ($this->getUser() !== null) {
			$em->flush();
			$sessionQuizz->remove();
		}

		return $this->render('quizz/result.html.twig', [
			'result' => $historic
		]);
	}


	/**
	 * @Route("/add", name="add")
	 */
	public function add(Request $request): Response
	{
		$quizz = new Quizz();
		$form = $this->createForm(QuizzType::class, $quizz);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {

			$data = $request->request->all();
			$em = $this->getDoctrine()->getManager();

			$quizz->setCreatedBy($this->getUser());
			$quizz->setDescription($data['quizz']['description']);
			$em->persist($quizz);



			dump($data);
			//$question = $data['quizz']['questions'];

			$question = new Question;
			$question->setQuestion($data['quizz']['questions']);
			$question->setCreatedBy($this->getUser());
			$question->addQuizz($quizz);

			$em->persist($question);

			//dd($question);

			$em->flush();

			$this->addFlash('success', 'votre quizz a bien été ajouté');
			return $this->redirectToRoute('home');
		}

		return $this->render('quizz/add.html.twig', [

			'form' => $form->createView()
		]);
	}

	/**
	 * Get the value of quizz
	 */
	public function getQuizz()
	{
		return $this->quizz;
	}

	/**
	 * Set the value of quizz
	 *
	 * @return  self
	 */
	public function setQuizz($quizz)
	{
		$this->quizz = $quizz;

		return $this;
	}
}
