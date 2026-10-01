import assert from 'node:assert/strict';
import test from 'node:test';
import {
    profileBackground,
    reportNeedsDescription,
} from '../../public/community.js';

await test('every Other severity requires context, ordinary reasons do not', () => {
    for (const reason of ['other_severe', 'other_moderate', 'other_minor']) {
        assert.equal(reportNeedsDescription(reason), true);
    }
    for (const reason of ['', 'spam', 'threats', 'harassment']) {
        assert.equal(reportNeedsDescription(reason), false);
    }
});

await test('background previews support default solid and gradient safely', () => {
    assert.equal(profileBackground('solid', '#123456', '#abcdef'), '#123456');
    assert.equal(
        profileBackground('gradient', '#123456', '#abcdef'),
        'linear-gradient(135deg, #123456, #abcdef)',
    );
    assert.equal(profileBackground('default', '#123456', '#abcdef'), '#f8f6fa');
    assert.equal(
        profileBackground(
            'solid',
            'red; background: url(https://example.com)',
            '#abcdef',
        ),
        '#f8f6fa',
    );
});
