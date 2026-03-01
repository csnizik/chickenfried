<?php

declare(strict_types=1);

namespace Drupal\ars_apps_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\webform\Entity\Webform;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides contextual help sidebar for webform wizards.
 *
 * Reads content from markup elements named {page_key}_contextual_help
 * on each wizard page and displays in sidebar region.
 *
 * Usage:
 * 1. Place this block in sidebar_second region.
 * 2. Configure with the webform machine name.
 * 3. In webform, add Processed Text elements named {page_key}_contextual_help
 *    (e.g., general_contextual_help, sequences_contextual_help).
 * 4. Content is automatically hidden from form and shown in sidebar.
 *
 * @Block(
 *   id = "webform_contextual_help",
 *   admin_label = @Translation("Webform Contextual Help"),
 *   category = @Translation("ARS Apps")
 * )
 */
final class WebformContextualHelpBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a WebformContextualHelpBlock.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RequestStack $request_stack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'webform_id' => '',
      'heading' => '',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Provides configuration form for webform ID and optional heading.
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['webform_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webform ID'),
      '#description' => $this->t('Machine name of the webform. Find at Structure > Webforms.'),
      '#default_value' => $this->configuration['webform_id'],
      '#required' => TRUE,
    ];

    $form['heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Heading'),
      '#description' => $this->t('Optional heading displayed above content. <br><strong>NOTE:</strong> a heading value here will show up on ALL instances of contextual help summary boxes, probably NOT what you want. Instead, consider skipping passing the heading as a prop and instead include h4.usa-summary-box__heading with your heading in the body. See USWDS example of summary box.<br><strong>TECH DEBT:</strong> refactor to properly use the heading prop of the summary box component.'),
      '#default_value' => $this->configuration['heading'],
    ];

    $form['help'] = [
      '#type' => 'details',
      '#title' => $this->t('Setup instructions'),
      '#open' => FALSE,
      'content' => [
        '#markup' => '
          <p>In your webform, add a <strong>Advanced HTML/Text</strong> element to each wizard page named:</p>
          <code>{page_key}_contextual_help</code>
          <p>Example: <code>general_contextual_help</code>, <code>sequences_contextual_help</code></p>
          <p>These elements are hidden from the form and rendered in this sidebar block.</p>
        ',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Saves the webform ID and heading configuration values.
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['webform_id'] = $form_state->getValue('webform_id');
    $this->configuration['heading'] = $form_state->getValue('heading');
  }

  /**
   * {@inheritdoc}
   *
   * Builds the contextual help block for the current webform wizard page.
   *
   * Loads the configured webform and finds the current wizard page based on
   * the 'page' query parameter. Then looks for a markup element named
   * {page_key}_contextual_help and renders its content.
   *
   * @return array
   *   A render array containing the contextual help markup, or empty array
   *   if no help content is found for the current page.
   */
  public function build(): array {
    $webform_id = $this->configuration['webform_id'];
    if (empty($webform_id)) {
      return [];
    }

    $webform = Webform::load($webform_id);
    if (!$webform) {
      return [];
    }

    // Get wizard pages.
    $pages = $webform->getPages();
    $page_keys = array_keys($pages);

    // Get current page from query param (can be number or key).
    $page_param = $this->requestStack->getCurrentRequest()->query->get('page');

    // Determine current page key.
    if (empty($page_param)) {
      // No param = first page.
      $current_page = $page_keys[0] ?? '';
      // Skip 'webform_start' if present.
      if ($current_page === 'webform_start' && isset($page_keys[1])) {
        $current_page = $page_keys[1];
      }
    }
    elseif (is_numeric($page_param)) {
      // Numeric param = page index (1-based in URL).
      $index = (int) $page_param - 1;
      $current_page = $page_keys[$index] ?? $page_keys[0] ?? '';
    }
    else {
      // String param = page key directly.
      $current_page = $page_param;
    }

    // Find element named {page_key}_contextual_help.
    $element_key = $current_page . '_contextual_help';
    $elements = $webform->getElementsDecodedAndFlattened();

    if (!isset($elements[$element_key])) {
      return [];
    }

    $element = $elements[$element_key];
    $markup = $element['#markup'] ?? $element['#text'] ?? '';

    if (empty($markup)) {
      return [];
    }

    return [
      '#markup' => $markup,
      '#current_page' => $current_page,
      '#attached' => [
        'library' => ['ars_apps_core/webform_contextual_help'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Adds 'url.query_args:page' cache context to vary by wizard page.
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.query_args:page']);
  }

  /**
   * {@inheritdoc}
   *
   * Adds webform cache tag to invalidate when webform changes.
   */
  public function getCacheTags(): array {
    $tags = parent::getCacheTags();
    if (!empty($this->configuration['webform_id'])) {
      $tags[] = 'webform:' . $this->configuration['webform_id'];
    }
    return $tags;
  }

}
