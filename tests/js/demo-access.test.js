import test from 'node:test';
import assert from 'node:assert/strict';

import { can, currentAccess, hasPermission } from '../../resources/js/demo-access.js';

test('permission helper grants exact permissions and administrator wildcard', () => {
    assert.equal(hasPermission(['drafts.manage'], 'drafts.manage'), true);
    assert.equal(hasPermission(['drafts.manage'], 'transactions.approve'), false);
    assert.equal(hasPermission(['*'], 'users.manage'), true);
});

test('permission helper fails closed without browser access JSON', () => {
    assert.deepEqual(currentAccess(), { role: '', permissions: [] });
    assert.equal(can('drafts.manage'), false);
});
