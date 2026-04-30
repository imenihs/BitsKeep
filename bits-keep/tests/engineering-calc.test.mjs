import assert from 'node:assert/strict';
import { __engineeringCalcTest as calc } from '../resources/js/pages/engineering-calc.js';

const valueOf = (expr) => calc.evaluateProgram(expr).value;

assert.equal(calc.formatNum(1e-10), '1e-10');
assert.equal(calc.formatValue(valueOf('1 << 40')), '1,099,511,627,776');
assert.equal(valueOf('1 << 40'), 1099511627776);
assert.equal(valueOf('2^53 + 1'), 9007199254740993n);
assert.equal(calc.formatValue(valueOf('2^53 + 1')), '9,007,199,254,740,993');
assert.equal(calc.formatValue(valueOf('round(9007199254740992.1)')), 'Number安全整数範囲外のため整数表示を抑制');
assert.equal(valueOf('0x20000000000001 | 0'), 9007199254740993n);
assert.equal(valueOf('and(0x20000000000001, 0x20000000000001)'), 9007199254740993n);
assert.equal(calc.exactDisplayInt(9007199254740992), null);
assert.equal(calc.isUnsafeIntegerNumber(9007199254740992), true);
assert.equal(valueOf('0x100000000 | 1'), 4294967297);
assert.equal(valueOf('and(0x100000001, 0x100000001)'), 4294967297);
assert.equal(valueOf('(0b101101 << 2) | 0x03'), 183);
assert.equal(Math.round(valueOf('solve(sqrt(x)=2, x)')), 4);

try {
    valueOf('solve(x*x+1=0, x)');
    assert.fail('solve without a real root should fail');
} catch (error) {
    assert.match(error.message, /収束/);
}

assert.equal(valueOf('parallel(a, b) = 1 / ((1 / a) + (1 / b))\nparallel(10k, 22k)'), 6875);
assert.equal(valueOf('avg([10k, 11k, 9.8k])'), 10266.666666666666);
assert.equal(valueOf('rgb(64, 128, 255)'), '#4080FF');
assert.equal(valueOf('dateDiff("2026-04-30", "2026-05-02", "day")'), 2);

try {
    valueOf('\n\nmissing + 1');
    assert.fail('missing symbol should fail');
} catch (error) {
    assert.equal(error.lineNumber, 3);
}

console.log('engineering calc assertions passed');
