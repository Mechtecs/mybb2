<?php
/**
 * Forum repository implementation, using Eloquent ORM.
 *
 * @author    MyBB Group
 * @version   2.0.0
 * @package   mybb/core
 * @license   http://www.mybb.com/licenses/bsd3 BSD-3
 */

namespace MyBB\Core\Database\Repositories\Eloquent;

use MyBB\Core\Database\Models\Forum;
use MyBB\Core\Database\Models\Post;
use MyBB\Core\Database\Models\Topic;
use MyBB\Core\Database\Repositories\ForumRepositoryInterface;
use MyBB\Core\Permissions\PermissionChecker;

class ForumRepository implements ForumRepositoryInterface
{
    /**
     * @var Forum $forumModel
     */
    protected $forumModel;

    /**
     * @var PermissionChecker
     */
    private $permissionChecker;

    /**
     * @param Forum $forumModel                    The model to use for forums.
     * @param PermissionChecker $permissionChecker The permission class
     */
    public function __construct(
        Forum $forumModel,
        PermissionChecker $permissionChecker
    ) {
        $this->forumModel = $forumModel;
        $this->permissionChecker = $permissionChecker;
    }

    /**
     * Get all forums.
     *
     * @return mixed
     */
    public function all()
    {
        return $this->forumModel->all();
    }


    /**
     * Find a single forum by ID.
     *
     * @param int $id The ID of the forum to find.
     *
     * @return mixed
     */
    public function find($id = 0)
    {
        $unviewable = $this->permissionChecker->getUnviewableIdsForContent('forum');

        $parent = $this->forumModel
            ->select('left_id', 'right_id')
            ->whereNotIn('id', $unviewable)
            ->find($id);

        if (!$parent) {
            return $parent;
        }

        $forums = $this->forumModel
            ->with(['lastPost', 'lastPost.topic', 'lastPostAuthor'])
            ->whereNotIn('id', $unviewable)
            ->whereBetween('left_id', [$parent->left_id, $parent->right_id])
            ->get();

        return $forums->toTree();
    }

    /**
     * Find a single forum by its slug.
     *
     * @param string $slug The slug of the forum. Eg: 'my-first-forum'.
     *
     * @return mixed
     */
    public function findBySlug($slug = '')
    {
        $unviewable = $this->permissionChecker->getUnviewableIdsForContent('forum');

        $parent = $this->forumModel
            ->select('left_id', 'right_id')
            ->whereNotIn('id', $unviewable)
            ->whereSlug($slug)
            ->first();

        if (!$parent) {
            return $parent;
        }

        $forums = $this->forumModel
            ->with(['lastPost', 'lastPost.topic', 'lastPostAuthor'])
            ->whereNotIn('id', $unviewable)
            ->whereBetween('left_id', [$parent->left_id, $parent->right_id])
            ->get();

        return $forums->toTree();
    }

    /**
     * Get the forum tree for the index, consisting of root forums (categories), and one level of descendants.
     *
     * @param bool $checkPermissions
     *
     * @return mixed
     */
    public function getIndexTree($checkPermissions = true)
    {
        // TODO: The caching decorator would also cache the relations here
        $baseQuery = $this->forumModel;

        if ($checkPermissions) {
            $unviewable = $this->permissionChecker->getUnviewableIdsForContent('forum');
            $baseQuery = $baseQuery->whereNotIn('id', $unviewable);
        }

        $res = $baseQuery->with([
            'lastPost',
            'lastPost.topic',
            'lastPostAuthor',
        ])->get();
        return $res->toTree();
    }

    /**
     * Increment the number of posts in the forum by one.
     *
     * @param int $id The ID of the forum to increment the post count for.
     *
     * @return mixed
     */
    public function incrementPostCount($id = 0)
    {
        $forum = $this->find($id);

        if ($forum) {
            $forum->increment('num_posts');
        }

        return $forum;
    }

    /**
     * Increment the number of topics in the forum by one.
     *
     * @param int $id The ID of the forum to increment the topic count for.
     *
     * @return mixed
     */
    public function incrementTopicCount($id = 0)
    {
        $forum = $this->find($id);

        if ($forum) {
            $forum->increment('num_topics');
        }

        return $forum;
    }

    /**
     * Update the last post for this forum
     *
     * @param Forum $forum The forum to update
     * @param Post $post
     *
     * @return mixed
     */
    public function updateLastPost(Forum $forum, Post $post = null)
    {
        if ($post === null) {
            $topic = $forum->topics->sortByDesc('last_post_id')->first();
            if ($topic != null) {
                $post = $topic->lastPost;
            }
        }

        if ($post != null) {
            $forum->update([
                'last_post_id'      => $post->id,
                'last_post_user_id' => $post->user_id,
            ]);
        } else {
            $forum->update([
                'last_post_id'      => null,
                'last_post_user_id' => null,
            ]);
        }

        return $forum;
    }

    /**
     * @param Topic $topic
     * @param Forum $forum
     */
    public function moveTopicToForum(Topic $topic, Forum $forum)
    {
        $topic->forum->decrement('num_topics');
        $topic->forum->decrement('num_posts', $topic->num_posts);

        $topic->forum_id = $forum->id;
        $topic->save();

        $topic->forum->increment('num_topics');
        $topic->forum->increment('num_posts', $topic->num_posts);

        $this->updateLastPost($forum);
    }
}
