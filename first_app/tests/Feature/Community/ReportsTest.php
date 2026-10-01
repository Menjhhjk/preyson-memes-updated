<?php

use App\Models\Comment;
use App\Models\Report;
use App\Models\User;
use App\Support\ReportReasons;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/CommunityHelpers.php';

beforeEach(fn () => Storage::fake('public'));

function communityReport(string $type, int $id, string $reason = 'spam', array $attributes = []): Report
{
    return Report::create([
        'target_type' => $type, 'target_id' => $id, 'target_label' => 'Reported '.$type,
        'target_snapshot' => 'Original content', 'reason' => $reason, 'severity' => ReportReasons::severity($reason),
        ...$attributes,
    ]);
}

test('members report posts comments and accounts with server derived severity', function () {
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $post = communityPost($owner);
    $comment = $post->comments()->create(['user_id' => $owner->id, 'body' => 'A reported comment']);
    foreach (['post' => $post, 'comment' => $comment, 'account' => $owner] as $type => $target) {
        $params = ['type' => $type, 'id' => $target->id];
        $this->actingAs($reporter)->get(route('reports.create', $params))->assertOk()
            ->assertSee('Severe')->assertSee('Moderate')->assertSee('Minor')->assertSee('Other');
        $this->post(route('reports.store', $params), ['reason' => 'harassment', 'severity' => 'severe', 'reporter_id' => $owner->id, 'description' => 'Some context'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reports', [
            'target_type' => $type, 'target_id' => $target->id, 'reporter_id' => $reporter->id,
            'reason' => 'harassment', 'severity' => 'moderate', 'description' => 'Some context', 'status' => 'open',
        ]);
    }
});

test('Other always requires a description and ordinary reasons accept optional descriptions', function () {
    $post = communityPost(User::factory()->create());
    $this->actingAs(User::factory()->create());
    $url = route('reports.store', ['type' => 'post', 'id' => $post->id]);
    foreach (['other_severe', 'other_moderate', 'other_minor'] as $reason) {
        $this->post($url, ['reason' => $reason, 'description' => ' '])->assertSessionHasErrors('description');
    }
    $this->post($url, ['reason' => 'unknown', 'description' => str_repeat('x', 3001)])->assertSessionHasErrors(['reason', 'description']);
    expect(Report::count())->toBe(0);
    $this->post($url, ['reason' => 'spam'])->assertSessionHasNoErrors();
    expect(Report::sole()->description)->toBeNull();
    $this->actingAs(User::factory()->create())->post($url, ['reason' => 'other_severe', 'description' => 'Details of an urgent concern'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('reports', ['reason' => 'other_severe', 'severity' => 'severe']);
});

test('reports are limited to one per member per target even after review', function () {
    $post = communityPost(User::factory()->create());
    $this->actingAs(User::factory()->create());
    $params = ['type' => 'post', 'id' => $post->id];
    $this->post(route('reports.store', $params), ['reason' => 'spam'])->assertSessionHasNoErrors();
    Report::sole()->update(['status' => 'resolved']);
    $this->post(route('reports.store', $params), ['reason' => 'threats'])->assertSessionHasErrors('report');
    $this->get(route('reports.create', $params))->assertSee('already reported')->assertSee('resolved');
    expect(Report::count())->toBe(1);
});

test('only staff can access logs rankings and review actions and members cannot see reporter identities', function () {
    $reporter = User::factory()->create(['username' => 'secret_reporter']);
    $owner = User::factory()->create();
    $post = communityPost($owner);
    $report = communityReport('post', $post->id, 'spam', ['reporter_id' => $reporter->id, 'description' => 'Staff-only description']);
    $this->get(route('reports.index'))->assertRedirect(route('login'));
    $this->post(route('reports.store', ['type' => 'post', 'id' => $post->id]), ['reason' => 'spam'])->assertRedirect(route('login'));
    $this->actingAs($owner)->get(route('posts.show', $post))->assertDontSee('secret_reporter')->assertDontSee('Staff-only description')->assertDontSee('Report logs');
    $this->get(route('reports.index'))->assertForbidden();
    $this->get(route('reports.leaderboard'))->assertForbidden();
    $this->patch(route('reports.update', $report), ['status' => 'dismissed'])->assertForbidden();
    foreach (['moderator', 'admin'] as $role) {
        $staff = User::factory()->create(['role' => $role]);
        $this->actingAs($staff)->get(route('reports.index'))->assertOk()->assertSee('secret_reporter')->assertSee('Staff-only description');
        $this->get(route('reports.leaderboard'))->assertOk()->assertSee($post->title);
        $this->patch(route('reports.update', $report), ['status' => 'in_review', 'moderator_note' => '<b>Checked</b>'])->assertSessionHasNoErrors();
        expect($report->fresh()->reviewed_by)->toBe($staff->id)->and($report->fresh()->reviewed_at)->not->toBeNull();
        $this->get(route('reports.index'))->assertSee('<b>Checked</b>')->assertDontSee('<b>Checked</b>', false);
    }
});

test('severity priority beats volume while weighted ranking uses 100 10 and 1', function () {
    $owner = User::factory()->create();
    $minor = communityPost($owner, ['title' => 'Many minor']);
    $moderate = communityPost($owner, ['title' => 'Many moderate']);
    $severe = communityPost($owner, ['title' => 'One severe']);
    foreach (range(1, 111) as $unused) {
        communityReport('post', $minor->id, 'spam');
    }
    foreach (range(1, 11) as $unused) {
        communityReport('post', $moderate->id, 'harassment');
    }
    communityReport('post', $severe->id, 'threats');
    $this->actingAs(User::factory()->create(['role' => 'moderator']));
    $priority = $this->get(route('reports.leaderboard'))->assertOk()->viewData('rankings');
    expect($priority->pluck('target_id')->all())->toBe([$severe->id, $moderate->id, $minor->id]);
    $weighted = $this->get(route('reports.leaderboard', ['sort' => 'weighted']))->assertOk()->viewData('rankings');
    expect($weighted->pluck('target_id')->all())->toBe([$minor->id, $moderate->id, $severe->id]);
    expect($weighted->pluck('score')->map(fn ($score) => (int) $score)->all())->toBe([111, 110, 100]);
});

test('logs and rankings filter types severity reason status dates search and drilldown', function () {
    $owner = User::factory()->create();
    $post = communityPost($owner);
    $comment = $post->comments()->create(['user_id' => $owner->id, 'body' => 'Example']);
    $one = communityReport('post', $post->id, 'spam', ['target_label' => 'Literal 100%_title', 'created_at' => '2026-09-15 12:00:00']);
    $two = communityReport('comment', $comment->id, 'threats', ['target_label' => 'Another comment', 'created_at' => '2026-09-16 12:00:00']);
    $resolved = communityReport('account', $owner->id, 'scam', ['status' => 'resolved', 'created_at' => '2026-09-17 12:00:00']);
    $this->actingAs(User::factory()->create(['role' => 'moderator']));
    $cases = [
        [['type' => 'post'], [$one->id]], [['type' => 'comment'], [$two->id]],
        [['severity' => 'severe'], [$two->id]], [['reason' => 'spam'], [$one->id]],
        [['status' => 'resolved'], [$resolved->id]], [['q' => '%_'], [$one->id]],
        [['from' => '2026-09-16', 'until' => '2026-09-16'], [$two->id]],
        [['until' => '2026-09-15'], [$one->id]], [['type' => 'post', 'target' => $post->id], [$one->id]],
    ];
    foreach ($cases as [$filters, $ids]) {
        $reports = $this->get(route('reports.index', $filters))->assertOk()->viewData('reports');
        expect($reports->modelKeys())->toBe($ids);
        $this->get(route('reports.leaderboard', [...$filters, 'status' => $filters['status'] ?? 'all']))->assertOk()->assertViewHas('rankings', fn ($rows) => $rows->total() === 1);
    }
    $this->get(route('reports.leaderboard'))->assertViewHas('rankings', fn ($rows) => $rows->total() === 2);
    $this->get(route('reports.leaderboard', ['status' => 'all']))->assertViewHas('rankings', fn ($rows) => $rows->total() === 3);
    $this->get(route('reports.index', ['sort' => 'oldest']))->assertViewHas('reports', fn ($rows) => $rows->modelKeys() === [$one->id, $two->id, $resolved->id]);
    $this->get(route('reports.index', ['sort' => 'severity']))->assertViewHas('reports', fn ($rows) => $rows->modelKeys() === [$two->id, $resolved->id, $one->id]);
});

test('target types never combine even when numeric identifiers match and reason breakdowns count correctly', function () {
    $member = User::factory()->create();
    $post = communityPost($member, ['id' => $member->id]);
    $comment = Comment::forceCreate(['id' => $member->id, 'post_id' => $post->id, 'user_id' => $member->id, 'body' => 'One']);
    communityReport('post', $post->id, 'spam');
    communityReport('post', $post->id, 'off_topic');
    communityReport('comment', $comment->id, 'spam');
    communityReport('account', $member->id, 'spam');
    $response = $this->actingAs(User::factory()->create(['role' => 'moderator']))->get(route('reports.leaderboard'))->assertOk();
    expect($response->viewData('rankings')->total())->toBe(3);
    $breakdown = $response->viewData('breakdowns')['post:'.$post->id];
    expect($breakdown)->toHaveCount(2)->and($breakdown['off_topic'])->toBe(1)->and($breakdown['spam'])->toBe(1);
});

test('deleting comments posts or accounts retains snapshots and anonymizes removed reporters', function () {
    $member = User::factory()->create();
    $post = communityPost($member);
    $comment = $post->comments()->create(['user_id' => $member->id, 'body' => 'Original comment']);
    foreach (['post' => $post, 'comment' => $comment, 'account' => $member] as $type => $target) {
        communityReport($type, $target->id, 'spam', ['reporter_id' => $member->id]);
    }
    $post->delete();
    $member->delete();
    expect(Comment::count())->toBe(0)->and(Report::count())->toBe(3)->and(Report::whereNotNull('reporter_id')->count())->toBe(0);
    $this->actingAs(User::factory()->create(['role' => 'moderator']))->get(route('reports.index'))->assertOk()->assertSee('Content removed')->assertSee('Original content')->assertSee('Former member');
    $this->get(route('reports.leaderboard'))->assertOk()->assertSee('reports retained');
});

test('report entry points reject invalid targets and throttle mass reporting', function () {
    $member = User::factory()->create();
    $this->actingAs($member);
    $this->get('/report/database/1')->assertNotFound();
    $this->get('/report/post/999999')->assertNotFound();
    foreach (range(1, 10) as $number) {
        $post = communityPost($member);
        $this->post(route('reports.store', ['type' => 'post', 'id' => $post->id]), ['reason' => 'spam'])->assertSessionHasNoErrors()->assertRedirect();
    }
    $post = communityPost($member);
    $this->post(route('reports.store', ['type' => 'post', 'id' => $post->id]), ['reason' => 'spam'])->assertStatus(429);
});
