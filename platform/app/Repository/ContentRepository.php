<?php
namespace Onay\App\Repository;

use Onay\App\Kernel\Database;

/**
 * Sayfalar, blog yazilari ve kategoriler. Yayinda olmayan icerik ziyaretciye
 * hicbir yerde gorunmez; bu filtre depoda uygulanir ki her cagri yerinde
 * tekrar dusunulmesin.
 */
final class ContentRepository
{
    // --- Sayfalar -------------------------------------------------------

    /** @return array<int, array<string,mixed>> */
    public function pages(bool $yalnizYayinda = true): array
    {
        $sql = 'SELECT * FROM pages';
        if ($yalnizYayinda) {
            $sql .= " WHERE status = 'published'";
        }

        return Database::pdo()->query($sql . ' ORDER BY menu_order, title')->fetchAll();
    }

    /** Ust menude gosterilecek sayfalar. */
    public function menuPages(): array
    {
        return Database::pdo()->query(
            "SELECT title, slug FROM pages
             WHERE status = 'published' AND show_in_menu = 1
             ORDER BY menu_order, title"
        )->fetchAll();
    }

    public function page(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM pages WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function pageBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published'");
        $stmt->execute([$slug]);

        return $stmt->fetch() ?: null;
    }

    public function savePage(?int $id, array $veri): int
    {
        $simdi = date('Y-m-d H:i:s');

        if ($id === null) {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO pages (title, slug, content, meta_description, status, show_in_menu, menu_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $veri['title'], $veri['slug'], $veri['content'], $veri['meta_description'],
                $veri['status'], $veri['show_in_menu'], $veri['menu_order'], $simdi, $simdi,
            ]);

            return (int) Database::pdo()->lastInsertId();
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE pages SET title = ?, slug = ?, content = ?, meta_description = ?,
                    status = ?, show_in_menu = ?, menu_order = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([
            $veri['title'], $veri['slug'], $veri['content'], $veri['meta_description'],
            $veri['status'], $veri['show_in_menu'], $veri['menu_order'], $simdi, $id,
        ]);

        return $id;
    }

    public function deletePage(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
    }

    // --- Yazilar --------------------------------------------------------

    /** @return array<int, array<string,mixed>> */
    public function posts(bool $yalnizYayinda = true, ?int $kategoriId = null, int $limit = 50): array
    {
        $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
                FROM posts p LEFT JOIN post_categories c ON c.id = p.category_id';
        $kosul = [];
        $params = [];

        if ($yalnizYayinda) {
            $kosul[] = "p.status = 'published'";
            $kosul[] = '(p.published_at IS NULL OR p.published_at <= ?)';
            $params[] = date('Y-m-d H:i:s');
        }
        if ($kategoriId !== null) {
            $kosul[] = 'p.category_id = ?';
            $params[] = $kategoriId;
        }
        if ($kosul !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $kosul);
        }

        $sql .= ' ORDER BY COALESCE(p.published_at, p.created_at) DESC LIMIT ' . max(1, $limit);

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function post(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function postBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM posts p LEFT JOIN post_categories c ON c.id = p.category_id
             WHERE p.slug = ? AND p.status = 'published'"
        );
        $stmt->execute([$slug]);

        return $stmt->fetch() ?: null;
    }

    public function savePost(?int $id, array $veri): int
    {
        $simdi = date('Y-m-d H:i:s');

        if ($id === null) {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO posts (category_id, title, slug, excerpt, content, meta_description, status, published_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $veri['category_id'], $veri['title'], $veri['slug'], $veri['excerpt'], $veri['content'],
                $veri['meta_description'], $veri['status'], $veri['published_at'], $simdi, $simdi,
            ]);

            return (int) Database::pdo()->lastInsertId();
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE posts SET category_id = ?, title = ?, slug = ?, excerpt = ?, content = ?,
                    meta_description = ?, status = ?, published_at = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([
            $veri['category_id'], $veri['title'], $veri['slug'], $veri['excerpt'], $veri['content'],
            $veri['meta_description'], $veri['status'], $veri['published_at'], $simdi, $id,
        ]);

        return $id;
    }

    public function deletePost(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
    }

    // --- Kategoriler ----------------------------------------------------

    /** @return array<int, array<string,mixed>> */
    public function categories(): array
    {
        return Database::pdo()->query(
            'SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id) AS post_count
             FROM post_categories c ORDER BY c.sort_order, c.name'
        )->fetchAll();
    }

    public function categoryBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM post_categories WHERE slug = ?');
        $stmt->execute([$slug]);

        return $stmt->fetch() ?: null;
    }

    public function saveCategory(?int $id, string $ad, string $slug, int $sira): int
    {
        if ($id === null) {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO post_categories (name, slug, sort_order) VALUES (?, ?, ?)'
            );
            $stmt->execute([$ad, $slug, $sira]);

            return (int) Database::pdo()->lastInsertId();
        }

        Database::pdo()->prepare('UPDATE post_categories SET name = ?, slug = ?, sort_order = ? WHERE id = ?')
            ->execute([$ad, $slug, $sira, $id]);

        return $id;
    }

    public function deleteCategory(int $id): void
    {
        // Yazilar silinmez, kategorisiz kalir.
        Database::pdo()->prepare('UPDATE posts SET category_id = NULL WHERE category_id = ?')->execute([$id]);
        Database::pdo()->prepare('DELETE FROM post_categories WHERE id = ?')->execute([$id]);
    }

    /** Baslikltan URL parcasi uretir; Turkce harfler karsiligina cevrilir. */
    public static function slugify(string $metin): string
    {
        $harita = ['ı'=>'i','İ'=>'i','ş'=>'s','Ş'=>'s','ğ'=>'g','Ğ'=>'g',
                   'ü'=>'u','Ü'=>'u','ö'=>'o','Ö'=>'o','ç'=>'c','Ç'=>'c'];
        $metin = strtr($metin, $harita);
        $metin = mb_strtolower($metin, 'UTF-8');
        $metin = preg_replace('/[^a-z0-9]+/u', '-', $metin) ?? '';

        return trim($metin, '-') ?: 'icerik-' . substr(md5($metin . microtime()), 0, 6);
    }
}
