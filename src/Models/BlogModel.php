<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Json;
use PDO;

final class BlogModel extends BaseModel
{
    private const COLUMNS = [
        'id',
        'title',
        'slug',
        'category',
        'excerpt',
        'content',
        'author',
        'publish_date',
        'read_time',
        'html_title',
        'html_meta_tags',
        'image',
        'tags',
        'created_at',
        'updated_at'
    ];

    private BlogTopicModel $topics;

    public function __construct(PDO $db, ?BlogTopicModel $topics = null)
    {
        parent::__construct($db);
        $this->topics = $topics ?? new BlogTopicModel($db);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function all(array $options = []): array
    {
        $joins = '';
        $where = [];
        $params = [];

        if (!empty($options['topicSlug'])) {
            $joins .= ' INNER JOIN blog_topic_relations btr_filter ON blogs.id = btr_filter.blog_id';
            $joins .= ' INNER JOIN blog_topics bt_filter ON bt_filter.id = btr_filter.topic_id';
            $where[] = 'bt_filter.slug = :topicSlug';
            $params['topicSlug'] = $options['topicSlug'];
        }

        if (!empty($options['search'])) {
            $where[] = '(LOWER(blogs.title) LIKE :search OR LOWER(blogs.excerpt) LIKE :search OR LOWER(blogs.author) LIKE :search OR LOWER(blogs.category) LIKE :search)';
            $params['search'] = '%' . strtolower($options['search']) . '%';
        }

        $sql = sprintf('SELECT DISTINCT %s FROM blogs', $this->prefixedColumns('blogs'));
        if ($joins) {
            $sql .= $joins;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY blogs.publish_date DESC';

        if (!empty($options['limit'])) {
            $sql .= ' LIMIT :limit';
        }

        $statement = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        if (!empty($options['limit'])) {
            $statement->bindValue(':limit', (int) $options['limit'], PDO::PARAM_INT);
        }
        $statement->execute();

        $rows = $statement->fetchAll();

        return $this->attachTopics($rows);
    }

    public function findById(int $id): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM blogs WHERE id = :id LIMIT 1', implode(', ', self::COLUMNS))
        );
        $statement->execute(['id' => $id]);
        $rows = $statement->fetchAll();

        if (empty($rows)) {
            return null;
        }

        $blogs = $this->attachTopics($rows);

        return $blogs[0] ?? null;
    }

    public function findBySlug(string $slug): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM blogs WHERE slug = :slug LIMIT 1', implode(', ', self::COLUMNS))
        );
        $statement->execute(['slug' => strtolower($slug)]);
        $rows = $statement->fetchAll();

        if (empty($rows)) {
            return null;
        }

        $blogs = $this->attachTopics($rows);

        return $blogs[0] ?? null;
    }

    /**
     * @param array<string, mixed> $blog
     */
    public function create(array $blog): ?array
    {
        $payload = $this->toPayload($blog);
        $now = $this->timestamp();
        $payload['created_at'] = $now;
        $payload['updated_at'] = $now;
        $id = $this->insert('blogs', $payload);
        $this->syncTopics($id, $blog['topicIds'] ?? []);

        return $this->findById($id);
    }

    /**
     * @param array<string, mixed> $blog
     */
    public function updateBlog(int $id, array $blog): ?array
    {
        $payload = $this->toPayload($blog);
        $payload['updated_at'] = $this->timestamp();
        $this->updateById('blogs', $id, $payload);
        $this->syncTopics($id, $blog['topicIds'] ?? []);

        return $this->findById($id);
    }

    public function deleteBlog(int $id): void
    {
        $this->deleteById('blogs', $id);
        $statement = $this->db->prepare('DELETE FROM blog_topic_relations WHERE blog_id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $blog
     * @return array<string, mixed>
     */
    private function toPayload(array $blog): array
    {
        return [
            'title' => $blog['title'],
            'slug' => strtolower($blog['slug']),
            'category' => $blog['category'],
            'excerpt' => $blog['excerpt'],
            'content' => $blog['content'] ?? null,
            'author' => $blog['author'],
            'publish_date' => $blog['date'],
            'read_time' => $blog['readTime'] ?? null,
            'html_title' => $blog['htmlTitle'] ?? null,
            'html_meta_tags' => $blog['htmlMetaTags'] ?? null,
            'image' => $blog['image'] ?? null,
            'tags' => Json::encode($blog['tags'] ?? []),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function attachTopics(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $blogIds = array_map(static fn ($row) => (int) $row['id'], $rows);
        $topicsMap = $this->topics->getTopicsByBlogIds($blogIds);

        return array_map(function (array $row) use ($topicsMap) {
            $blogId = (int) $row['id'];
            $topics = $topicsMap[$blogId] ?? [];
            return $this->mapRow($row, $topics);
        }, $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $topics
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $topics): array
    {
        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'category' => $row['category'],
            'excerpt' => $row['excerpt'],
            'content' => $row['content'],
            'author' => $row['author'],
            'date' => $row['publish_date'],
            'readTime' => $row['read_time'],
            'image' => $row['image'],
            'htmlTitle' => $row['html_title'],
            'htmlMetaTags' => $row['html_meta_tags'],
            'tags' => Json::decode($row['tags']),
            'topics' => $topics,
            'topicIds' => array_map(static fn ($topic) => $topic['id'], $topics),
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    /**
     * @param array<int> $topicIds
     */
    private function syncTopics(int $blogId, array $topicIds): void
    {
        $statement = $this->db->prepare('DELETE FROM blog_topic_relations WHERE blog_id = :id');
        $statement->execute(['id' => $blogId]);

        $unique = [];
        foreach ($topicIds as $topicId) {
            $id = (int) $topicId;
            if ($id > 0) {
                $unique[$id] = $id;
            }
        }

        if (empty($unique)) {
            return;
        }

        $insert = $this->db->prepare('INSERT INTO blog_topic_relations (blog_id, topic_id) VALUES (:blog_id, :topic_id) ON DUPLICATE KEY UPDATE topic_id = topic_id');
        foreach ($unique as $topicId) {
            $insert->execute([
                'blog_id' => $blogId,
                'topic_id' => $topicId,
            ]);
        }
    }

    private function prefixedColumns(string $alias): string
    {
        return implode(', ', array_map(static fn ($column) => sprintf('%s.%s', $alias, $column), self::COLUMNS));
    }
}
