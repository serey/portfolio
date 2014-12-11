<?php

namespace Application\Bundle\DefaultBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Application\Bundle\DefaultBundle\Form\Type\PromotionOrderFormType;
/**
 * Default controller. For single actions for project
 *
 * @author Stepan Tanasiychuk <ceo@stfalcon.com>
 */
class DefaultController extends Controller
{
    /**
     * Categories/projects list
     *
     * @return array()
     * @Cache(expires="tomorrow")
     * @Route("/", name="homepage")
     * @Template()
     */
    public function indexAction()
    {
        $repository = $this->getDoctrine()->getManager()->getRepository('StfalconPortfolioBundle:Project');
        $projects = $repository->findProjectsForHomePage();

        return array('projects' => $projects);
    }


    /**
     * Contacts page
     *
     * @param Request $request
     *
     * @return array()
     * @Template()
     * @Route("/contacts", name="contacts")
     */
    public function contactsAction(Request $request)
    {
        // @todo: refact
        if ($this->has('application_default.menu.breadcrumbs')) {
            $breadcrumbs = $this->get('application_default.menu.breadcrumbs');
            $breadcrumbs->addChild('Контакты')->setCurrent(true);
        }

        $directOrderForm = $this->createForm('direct_order', []);

        if ($request->isMethod('post')) {
            $directOrderForm->handleRequest($request);
            if ($directOrderForm->isValid()) {
                $formData = $directOrderForm->getData();
                $container = $this->get('service_container');
                $attachments = [];
                if ($formData['attach']) {
                    /** @var UploadedFile $attach */
                    $attach = $formData['attach'];
                    $attachFile = $attach->move(realpath($container->getParameter('kernel.root_dir') . '/../attachments/'), $attach->getClientOriginalName());
                    $attachments[] = $attachFile;
                }

                $mailer_name = $container->getParameter('mailer_name');
                $mailer_notify = $container->getParameter('mailer_notify');
                $subject = $this->get('translator')->trans('Stfalcon.com direct order');
                if ($this->get('application_default.service.mailer')->send(
                    [$mailer_notify, $mailer_name],
                    $subject,
                    '@ApplicationDefault/emails/direct_order.html.twig',
                    $formData,
                    $attachments
                    )
                ) {
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'result'    => 'success',
                            'view'      => $this->renderView('@ApplicationDefault/Default/_direct_order_form_success.html.twig')
                        ]);
                    }

                    $request->getSession()->getFlashBag()->add('success', $this->get('translator')->trans('Спасибо! Мы с Вами свяжемся в ближайшее время.'));

                    return $this->redirect($this->generateUrl('contacts'));
                } else {
                    $request->getSession()->getFlashBag()->add('error', $this->get('translator')->trans('Произошла ошибка при отправке письма.'));
                }

            }
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'result'    => 'error',
                'view'      => $this->renderView('@ApplicationDefault/Default/_direct_order_form.html.twig', ['form' => $directOrderForm->createView()])
            ]);
        }

        return ['form' => $directOrderForm->createView()];
    }

    /**
     * Promotions apps page
     *
     * @Route("/promotion/apps", name="page_promotion_apps")
     */
    public function promotionAppsAction()
    {
        $form = $this->createForm(new PromotionOrderFormType());

        return $this->render(
            'ApplicationDefaultBundle:Default:promotion_apps.html.twig',
            ['form' => $form->createView()]
        );
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     *
     * @Route("/order/apps", name="order_apps")
     */
    public function orderAppsAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            throw $this->createNotFoundException('Not supported');
        }

        $form = $this->createForm(new PromotionOrderFormType());
        $form->handleRequest($request);
        $translated = $this->get('translator');

        if ($form->isValid()) {
            $data = $form->getData();
            $email = $data['email'];
            $name  = $data['name'];

            $container = $this->get('service_container');
            $mailer_name = $container->getParameter('mailer_name');
            $mailer_notify = $container->getParameter('mailer_notify');
            $subject = $translated->trans('Заявка на разработку мобильного приложения от "%email%"', ['%email%' => $email]);

            if ($this->get('application_default.service.mailer')->send(
                [$mailer_notify, $mailer_name],
                $subject,
                '@ApplicationDefault/emails/order_app.html.twig',
                [
                    'message' => $data['message'],
                    'name'    => $name,
                    'email'   => $email
                ]
            )) {
                return new JsonResponse(['status' => 'success']);
            } else {
                return new JsonResponse(['status' => 'error']);
            }
        } else {
            return new JsonResponse(['status' => 'error']);
        }
    }
}