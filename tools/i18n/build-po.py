#!/usr/bin/env python3
"""Fill languages/evive-forms-<locale>.po from languages/evive-forms.pot with a translations table.

Usage: python3 -I tools/i18n/build-po.py nl_NL
Prints how many entries were translated and lists the missing msgids.
"""
import importlib.util, pathlib, re, sys

root = pathlib.Path(__file__).resolve().parents[2]
locale = sys.argv[1]
spec = importlib.util.spec_from_file_location('tbl', root / 'tools/i18n' / f'{locale}.py')
tbl = importlib.util.module_from_spec(spec); spec.loader.exec_module(tbl)

def unq(s): return s.encode().decode('unicode_escape').encode('latin-1').decode('utf-8')
def q(s): return s.replace('\\', '\\\\').replace('"', '\\"')

pot = (root / 'languages/evive-forms.pot').read_text()
blocks = pot.split('\n\n')
header = blocks[0].replace('msgstr ""\n', 'msgstr ""\n"Language: %s\\n"\n"Plural-Forms: nplurals=2; plural=(n != 1);\\n"\n' % locale, 1)
out, done, missing = [header], 0, []
for b in blocks[1:]:
    if not b.strip(): continue
    ctx = re.search(r'^msgctxt "(.*)"$', b, re.M); mid = re.search(r'^msgid "(.*)"$', b, re.M)
    if not mid: continue
    msgid = unq(mid.group(1)); c = unq(ctx.group(1)) if ctx else ''
    tr = tbl.CTX.get((c, msgid), tbl.T.get(msgid))
    if tr is None:
        if not (msgid.isdigit() or msgid.startswith('http') or msgid.startswith('evive') or msgid in ('field', 'input', 'email', 'consent', 'form', 'contact', 'demo', 'request', 'newsletter')): missing.append(msgid)
        tr = ''
    else: done += 1
    out.append(re.sub(r'^msgstr ""$', 'msgstr "%s"' % q(tr), b, count=1, flags=re.M))
(root / 'languages' / f'evive-forms-{locale}.po').write_text('\n\n'.join(out).rstrip() + '\n')
print(f'{locale}: {done} translated, {len(missing)} missing')
for m in missing: print('  missing:', m)
