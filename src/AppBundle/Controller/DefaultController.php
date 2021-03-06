<?php

namespace AppBundle\Controller;

use AppBundle\Entity\JiraServer;
use AppBundle\Form\JiraServerType;
use AppBundle\Services\XLSExportService;
use JiraRestApi\JiraException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller {

  /**
   * @Route("/", name="index")
   */
  public function indexAction( Request $request ) {
    $request->getSession()->remove( 'server' );
    $js   = new JiraServer();
    $form = $this->createForm( JiraServerType::class, $js );

    $form->handleRequest( $request );

    if ( $form->isSubmitted() && $form->isValid() ) {

      $js->setBaseUrl( 'https://' . parse_url( $js->getBaseUrl(), PHP_URL_HOST ) );
      $request->getSession()->set( 'server', $js );

      return $this->redirectToRoute( 'projects' );
    }

    return $this->render( 'AppBundle:Default:index.html.twig', array( 'form' => $form->createView() ) );
  }

  /**
   * @Route("/projects", name="projects")
   */
  public function projectsAction( Request $request ) {
    /** @var JiraServer $server */
    $server = $request->getSession()->get( 'server' );

    if ( ! $server ) {
      return $this->redirectToRoute( 'index' );
    }

    try {
      $ws       = $this->get( 'worklog' );
      $projects = $ws->getProjects( $server );
    } catch ( JiraException $e ) {
      $this->addFlash( 'danger', 'Bad credentials.' );

      return $this->redirectToRoute( 'index' );
    }

    return $this->render( 'AppBundle:Default:projects.html.twig', array(
      'projects' => $projects,
      'server'   => $server
    ) );
  }

  /**
   * @Route("/{project}/worklog/{from}/{to}", name="worklog")
   *
   * @ParamConverter("from", options={"format": "Y-m-d"})
   * @ParamConverter("to", options={"format": "Y-m-d"})
   */
  public function worklogAction( Request $request, $project, \DateTime $from = null, \DateTime $to = null ) {

    /** @var JiraServer $server */
    $server = $request->getSession()->get( 'server' );

    $ws = $this->get( 'worklog' );

    if ( ! $server ) {
      return $this->redirectToRoute( 'index' );
    }
    $format = $request->query->get( 'format' );

    try {
      $sprints = $ws->getSprints( $server, $project );

      if ( ! $from ) {
        /** @var \DateTime $from */
        if ( $sprints ) {
          $from = clone $sprints[0]->startDate;
        } else {
          $from = new \DateTime( 'now' );
        }
      }

      if ( ! $to ) {
        /** @var \DateTime $to */
        if ( $sprints ) {
          $to = clone $sprints[0]->endDate;
        } else {
          $to = new \DateTime( 'now' );
        }
      }

      $from->setTime( 0, 0, 1 );
      $to->setTime( 23, 59, 59 );

      $logs = $ws->getWorklogs( $server, $project, $from, $to );
    } catch ( JiraException $e ) {
      $this->addFlash( 'danger', 'Error, maybe project not exists.' );

      return $this->redirectToRoute( 'index' );
    }

    $sprint = $request->get( 'sprint' );

    if ( ! $sprint ) {
      foreach ( $sprints as $s ) {
        if ( $s->startDate->format( 'd-m-Y' ) == $from->format( 'd-m-Y' ) and
             $s->endDate->format( 'd-m-Y' ) == $to->format( 'd-m-Y' ) ) {
          $sprint = $s;
          break;
        }
      }
    }

    $data = array(
      'from'    => $from,
      'to'      => $to,
      'server'  => $server,
      'project' => $project,
      'logs'    => $logs,
      'sprints' => $sprints,
      'sprint'  => $sprint
    );

    if ( $format == 'xls' ) {
      $exportService = $this->get( 'export_service' );

      $response = new Response( $exportService->getFile( $data, $format ) );
      $filename = $project;
      if ( $sprint ) {
        $filename .= '_' . $sprint->name;
      }
      $filename .= '_' . $from->format( 'd-m-Y' ) . '-' . $to->format( 'd-m-Y' );
      $filename .= '.xls';
      $response->headers->set( 'Content-Disposition', sprintf( 'attachment; filename="%s";', $filename ) );
      $response->headers->set( 'Content-Type', 'application/vnd.ms-excel; charset=utf-8' );

      return $response;
    } else if ( $format == 'csv' ) {
      $exportService = $this->get( 'export_service' );

      $response = new Response( $exportService->getFile( $data, $format ) );
      $filename = $project;
      if ( $sprint ) {
        $filename .= '_' . $sprint->name;
      }
      $filename .= '_' . $from->format( 'd-m-Y' ) . '-' . $to->format( 'd-m-Y' );
      $filename .= '.csv';
      $response->headers->set( 'Content-Disposition', sprintf( 'attachment; filename="%s";', $filename ) );
      $response->headers->set( 'Content-Type', 'text/csv; charset=utf-8' );

      return $response;
    } else if ( $format == 'pdf' ) {
      $exportService = $this->get( 'export_service' );

      $response = new Response( $exportService->getFile( $data, $format ) );
      $filename = $project;
      if ( $sprint ) {
        $filename .= '_' . $sprint->name;
      }
      $filename .= '_' . $from->format( 'd-m-Y' ) . '-' . $to->format( 'd-m-Y' );
      $filename .= '.pdf';
      $response->headers->set( 'Content-Disposition', sprintf( 'attachment; filename="%s";', $filename ) );
      $response->headers->set( 'Content-Type', 'application/pdf; charset=utf-8' );

      return $response;
    } else if ( $format == 'xlsx' ) {
      $exportService = $this->get( 'export_service' );

      $response = new Response( $exportService->getFile( $data, $format ) );
      $filename = $project;
      if ( $sprint ) {
        $filename .= '_' . $sprint->name;
      }
      $filename .= '_' . $from->format( 'd-m-Y' ) . '-' . $to->format( 'd-m-Y' );
      $filename .= '.xlsx';
      $response->headers->set( 'Content-Disposition', sprintf( 'attachment; filename="%s";', $filename ) );
      $response->headers->set( 'Content-Type', 'application/vnd.ms-excel; charset=utf-8' );

      return $response;
    } else {
      return $this->render( 'AppBundle:Default:worklog.html.twig', $data );
    }
  }
}
