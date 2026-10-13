"""Build only the audited theme source; validate the final archive contract."""
from pathlib import Path
import hashlib
import json
import re
import zipfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
VERSION = '8.7.7'


def main():
    source = ROOT / 'source'
    files = sorted(p for p in source.rglob('*') if p.is_file())
    assert ET.parse(source / 'install.xml').getroot().findtext('version') == VERSION
    assert (source / 'upload').is_dir()
    baseline = ROOT / 'baseline'
    baseline_zip = next(baseline.glob('*.zip'))
    with zipfile.ZipFile(baseline_zip) as original:
        expected = {n for n in original.namelist() if not n.endswith('/')}
    names = {p.relative_to(source).as_posix() for p in files}
    assert expected <= names, 'Baseline files missing: ' + repr(expected - names)
    hashes = {}
    for path in files:
        name = path.relative_to(source).as_posix()
        assert '..' not in Path(name).parts and not name.startswith('/')
        assert path.suffix.lower() not in {'.zip', '.env', '.log', '.pyc'}
        raw = path.read_bytes()
        if path.suffix.lower() in {'.php', '.twig', '.js', '.css', '.xml', '.txt'}:
            assert not raw.startswith(b'\xef\xbb\xbf'), name
            text = raw.decode('utf-8')
            assert '8.7.5' not in text, name
            if path.suffix.lower() == '.php':
                assert not re.search(r'\b(?:eval|base64_decode|gzinflate|gzuncompress|str_rot13|create_function|shell_exec|proc_open|passthru|system|assert)\s*\(', text), name
        hashes[name] = hashlib.sha256(raw).hexdigest()
    dist = ROOT / 'dist'
    dist.mkdir(exist_ok=True)
    archive = dist / ('Theme_CodeCart_Pro_v' + VERSION + '.ocmod.zip')
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as out:
        out.writestr('upload/', b'')
        for path in files:
            out.write(path, path.relative_to(source).as_posix())
    with zipfile.ZipFile(archive) as result:
        assert result.testzip() is None
        assert 'install.xml' in result.namelist() and 'upload/' in result.namelist()
        assert {n for n in result.namelist() if not n.endswith('/')} == names
        for name, digest in hashes.items():
            assert hashlib.sha256(result.read(name)).hexdigest() == digest, name
    report = {'version': VERSION, 'file_count': len(files), 'baseline_file_count': len(expected),
              'baseline_files_preserved': True, 'crc_passed': True,
              'archive_sha256': hashlib.sha256(archive.read_bytes()).hexdigest(),
              'bytes': archive.stat().st_size, 'file_sha256': hashes}
    evidence = ROOT / 'evidence/release'
    evidence.mkdir(parents=True, exist_ok=True)
    (evidence / 'package-integrity.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
    (dist / (archive.name + '.sha256')).write_text(report['archive_sha256'] + '  ' + archive.name + '\n', encoding='utf-8')
    print(json.dumps({k: v for k, v in report.items() if k != 'file_sha256'}, indent=2))


if __name__ == '__main__':
    main()
