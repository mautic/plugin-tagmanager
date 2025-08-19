<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTagManagerBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Tag;
use Mautic\LeadBundle\Entity\TagRepository;
use Mautic\UserBundle\Entity\Permission;
use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

class TagControllerTest extends MauticMysqlTestCase
{
    /**
     * @var TagRepository
     */
    private $tagRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $tagModel            = static::getContainer()->get('mautic.lead.model.tag');
        $this->tagRepository = $tagModel->getRepository();

        $tags = ['tag1', 'tag2', 'tag3', 'tag4', 'tag5'];

        foreach ($tags as $tagName) {
            $tag = new Tag();
            $tag->setTag($tagName);
            $this->em->persist($tag);
        }

        $this->em->flush();
    }

    /**
     * Get all results without filtering.
     */
    public function testIndexActionWhenNotFiltered(): void
    {
        $this->client->request('GET', '/s/tags');
        $clientResponse         = $this->client->getResponse();
        $clientResponseContent  = $clientResponse->getContent();

        $this->assertTrue($clientResponse->isOk(), 'Return code must be 200.');
        $this->assertStringContainsString('tag1', $clientResponseContent, 'The return must contain tag1');
        $this->assertStringContainsString('tag2', $clientResponseContent, 'The return must contain tag2');
    }

    /**
     * Get results with filtering.
     */
    public function testIndexActionWhenFiltered(): void
    {
        $this->client->request('GET', '/s/tags?search=tag1');
        $clientResponse         = $this->client->getResponse();
        $clientResponseContent  = $clientResponse->getContent();

        $this->assertTrue($clientResponse->isOk(), 'Return code must be 200.');
        $this->assertStringContainsString('tag1', $clientResponseContent, 'The return must contain tag1');
        $this->assertStringNotContainsString('tag2', $clientResponseContent, 'The return must not contain tag2');
    }

    public function testTagDeletion(): void
    {
        $tagId = $this->tagRepository->findOneBy([])->getId();
        $this->client->request('POST', '/s/tags/delete/'.$tagId);
        $clientResponse = $this->client->getResponse();

        $this->assertTrue($clientResponse->isOk(), 'Return code must be 200.');
        $this->assertSame($this->tagRepository->find($tagId), null, 'Assert that tag is deleted');
    }

    /**
     * Get tag's view page.
     */
    public function testViewAction(): void
    {
        $tag = $this->tagRepository->findOneBy([]);

        $this->client->request('GET', '/s/tags/view/'.$tag->getId());
        $clientResponse         = $this->client->getResponse();
        $clientResponseContent  = $clientResponse->getContent();
        $this->assertTrue($clientResponse->isOk(), 'Return code must be 200.');
        $this->assertStringContainsString($tag->getTag(), $clientResponseContent, 'The return must contain tag');
    }

    public function testViewActionNotFound(): void
    {
        $this->client->followRedirects(false);
        $this->client->request('GET', '/s/tags/view/99999');
        $clientResponse = $this->client->getResponse();
        $this->assertTrue($clientResponse->isRedirection(), 'Must be redirect response.');
    }

    /**
     * Get tag's edit page.
     */
    public function testEditAction(): void
    {
        $TagName = 'Test tag';
        $tag     = $this->tagRepository->findOneBy([]);

        $crawler                = $this->client->request('GET', '/s/tags/edit/'.$tag->getId());
        $clientResponse         = $this->client->getResponse();
        $clientResponseContent  = $clientResponse->getContent();
        $this->assertTrue($clientResponse->isOk(), 'Return code must be 200.');
        $this->assertStringContainsString('Edit tag: '.$tag->getTag(), $clientResponseContent, 'The return must contain \'Edit tag\' text');

        $form = $crawler->selectButton('Save & Close')->form();
        $form['tag_entity[tag]']->setValue($TagName);
        $this->client->submit($form);

        $this->assertSame(1, $this->tagRepository->count(['tag' => $TagName]));
    }

    public function testEditActionNotFound(): void
    {
        $this->client->followRedirects(false);
        $this->client->request('GET', '/s/tags/edit/99999');
        $clientResponse = $this->client->getResponse();
        $this->assertTrue($clientResponse->isRedirection(), 'Must be redirect response.');
    }

    /**
     * Get tag's create page.
     */
    public function testNewAction(): void
    {
        $TagName        = 'Test tag';
        $crawler        = $this->client->request('GET', '/s/tags/new');
        $clientResponse = $this->client->getResponse();
        $this->assertTrue($clientResponse->isOk(), 'Return code must be 200.');

        $form = $crawler->selectButton('Save')->form();
        $form['tag_entity[tag]']->setValue($TagName);
        $this->client->submit($form);

        $this->assertSame(1, $this->tagRepository->count(['tag' => $TagName]));
    }

    public function testNewActionValidation(): void
    {
        $crawler = $this->client->request('GET', '/s/tags/new');
        $this->assertTrue($this->client->getResponse()->isOk());

        $buttonCrawler  = $crawler->selectButton('Save');
        $form           = $buttonCrawler->form();
        $form->setValues(['tag_entity[tag]' => '']);
        $this->client->submit($form);
        Assert::assertTrue($this->client->getResponse()->isOk());
        Assert::assertStringContainsString('A value is required.', $this->client->getResponse()->getContent());
    }

    public function testNewActionDuplicateTag(): void
    {
        $TagName        = $this->tagRepository->findOneBy([])->getTag();
        $crawler        = $this->client->request('GET', '/s/tags/new');
        $clientResponse = $this->client->getResponse();
        $this->assertTrue($clientResponse->isOk(), 'Return code must be 200.');

        $form = $crawler->selectButton('Save')->form();
        $form['tag_entity[tag]']->setValue($TagName);
        $crawler = $this->client->submit($form);

        $this->assertStringContainsString($TagName.' has been updated!', strip_tags($crawler->text(null, false)), 'Must contain already exist.');
    }

    public function testBatchDeleteAction(): void
    {
        $tags   = $this->tagRepository->findAll();
        $tagsId = array_map(fn (Tag $tag) => $tag->getId(), $tags);
        $this->client->request('POST', '/s/tags/batchDelete?ids='.json_encode($tagsId));
        $this->assertTrue($this->client->getResponse()->isOk(), 'Return code must be 200.');
        $this->assertEmpty($this->tagRepository->count([]), 'All tags must be deleted.');
    }

    public function testEmptyTagShouldThrowValidationError(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/s/tags/new');
        Assert::assertTrue($this->client->getResponse()->isOk());

        $buttonCrawler  = $crawler->selectButton('Save & Close');
        $form           = $buttonCrawler->form();
        $form->setValues(['tag_entity[tag]' => '']);
        $this->client->submit($form);
        Assert::assertTrue($this->client->getResponse()->isOk());
        Assert::assertStringContainsString('A value is required.', $this->client->getResponse()->getContent());
    }

    public function testEditTagWithNoPermission(): void
    {
        // Create a user without tag manager permissions
        $role = $this->createRole(false);
        $user = $this->createUser($role);
        $this->loginUser($user);

        $tag     = $this->tagRepository->findOneBy([]);
        $this->client->request(Request::METHOD_GET, '/s/tags/edit/'.$tag->getId());
        $this->assertResponseStatusCodeSame(403, (string) $this->client->getResponse()->getStatusCode());
    }

    public function testMergeAction(): void
    {
        $tags    = $this->tagRepository->findAll();
        $mainTag = $tags[0];

        $this->client->request('GET', '/s/tags/merge/'.$mainTag->getId());
        $clientResponse = $this->client->getResponse();
        $this->assertTrue($clientResponse->isOk(), 'Return code must be 200.');

        $crawler = $this->client->getCrawler();
        $this->assertStringContainsString('Merge', $crawler->text());
    }

    public function testMergeActionExcludesCurrentTag(): void
    {
        $tags       = $this->tagRepository->findAll();
        $currentTag = $tags[0];

        $crawler        = $this->client->request('GET', '/s/tags/merge/'.$currentTag->getId());
        $clientResponse = $this->client->getResponse();
        $this->assertTrue($clientResponse->isOk(), 'Return code must be 200.');

        // Check that the form exists and has the correct structure
        $this->assertCount(1, $crawler->filter('form'));

        // Check that the form has the tag_to_merge field
        $this->assertCount(1, $crawler->filter('select[name="tag_merge[tag_to_merge]"]'));

        // Check that the form has hidden buttons (as designed for AJAX functionality)
        $this->assertCount(1, $crawler->filter('.hide'));
    }

    public function testMergeActionWithInvalidTag(): void
    {
        $this->client->request('GET', '/s/tags/merge/999999');
        $clientResponse = $this->client->getResponse();
        $this->assertTrue($clientResponse->isOk(), 'Return code must be 200 (redirect with error).');
    }

    public function testMergeActionPost(): void
    {
        $tags    = $this->tagRepository->findAll();
        $mainTag = $tags[0];
        $secTag  = $tags[1];

        // Test that the merge action returns the correct response
        $crawler  = $this->client->request('GET', '/s/tags/merge/'.$secTag->getId());
        $response = $this->client->getResponse();

        // Debug: check what status code and content we're getting
        $statusCode = $response->getStatusCode();
        $content    = $response->getContent();

        $this->assertTrue($response->isOk(), 'Return code must be 200. Got: '.$statusCode.'. Content: '.substr($content, 0, 500));

        // Check that the form exists and has the correct structure
        $this->assertCount(1, $crawler->filter('form'));

        // Check that the form has the tag_to_merge field
        $this->assertCount(1, $crawler->filter('select[name="tag_merge[tag_to_merge]"]'));

        // Check that the form has hidden buttons (as designed for AJAX functionality)
        $this->assertCount(1, $crawler->filter('.hide'));

        // Test the actual merge functionality by calling the model directly
        $tagModel = static::getContainer()->get('mautic.lead.model.tag');
        $tagModel->tagMerge($mainTag, $secTag);

        $this->em->clear();

        $remainingTags   = $this->tagRepository->findAll();
        $remainingTagIds = array_map(fn ($tag) => $tag->getId(), $remainingTags);

        $this->assertNotContains($secTag->getId(), $remainingTagIds, 'Secondary tag should be deleted');
        $this->assertContains($mainTag->getId(), $remainingTagIds, 'Main tag should still exist');
    }

    private function createRole(bool $isAdmin = false): Role
    {
        $role = new Role();
        $role->setName('Role');
        $role->setIsAdmin($isAdmin);

        // Only add tag manager permissions for admin users
        if ($isAdmin) {
            // Add required permissions for tag manager functionality
            // view (4) + edit (16) + delete (128) = 148
            $permission = new Permission();
            $permission->setBundle('tagManager');
            $permission->setName('tagManager');
            $permission->setBitwise(148);
            $permission->setRole($role);
            $this->em->persist($permission);
        }

        $this->em->persist($role);

        return $role;
    }

    private function createUser(Role $role): User
    {
        $user = new User();
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setUsername('testuser_'.microtime(true).'_'.bin2hex(random_bytes(8)));
        $user->setEmail('john.doe@email.com');
        $user->setPassword('password');
        $user->setRole($role);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
