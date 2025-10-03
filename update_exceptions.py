#!/usr/bin/env python3
"""
Update all GuzzleException catch blocks to include error_code and error_data extraction
"""
import re
import sys

def update_catch_blocks(content):
    """Update all catch blocks that don't already have errorData extraction"""

    # Pattern to match catch blocks that need updating
    # (those with extractErrorMessage but without extractErrorData)
    pattern = re.compile(
        r'(}\s+catch\s+\(GuzzleException\s+\$e\)\s+\{\s*\n'
        r'\s+\$errorMessage\s+=\s+\$this->extractErrorMessage\(\$e\);)\s*\n'
        r'(\s+\$this->logger->error\([^,]+,\s+\[[\'"]error[\'"]\s+=>\s+\$errorMessage)\];?\s*\n'
        r'(\s+throw\s+new\s+RagApiException\([^,]+,\s+\$e->getCode\(\),\s+\$e\));',
        re.MULTILINE
    )

    def replacement(match):
        indent = '            '  # Standard indentation
        error_msg_line = match.group(1)
        logger_line = match.group(2)
        throw_line = match.group(3)

        # Extract the error message from the throw statement
        error_match = re.search(r"throw\s+new\s+RagApiException\('([^']+)'", throw_line)
        if not error_match:
            # If it's a different pattern, keep the original
            return match.group(0)

        error_description = error_match.group(1)

        # Build the new catch block
        new_block = error_msg_line + '\n'
        new_block += indent + '$errorData = $this->extractErrorData($e);\n'
        new_block += indent + '$errorCode = $this->extractErrorCode($e);\n'
        new_block += logger_line + ", 'error_code' => $errorCode];\n"
        new_block += indent + f"throw new RagApiException(\n"
        new_block += indent + f"    '{error_description}' . \$errorMessage,\n"
        new_block += indent + f"    \$e->getCode(),\n"
        new_block += indent + f"    \$e,\n"
        new_block += indent + f"    null,\n"
        new_block += indent + f"    \$errorCode,\n"
        new_block += indent + f"    \$errorData\n"
        new_block += indent + f");"

        return new_block

    # Perform the replacement
    updated_content = pattern.sub(replacement, content)

    return updated_content

def process_file(filepath):
    """Process a single file"""
    print(f"Processing {filepath}...")

    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()

        # Check if already processed
        if 'extractErrorData' in content:
            # Count how many catch blocks still need updating
            pattern = re.compile(
                r'extractErrorMessage.*?\n.*?logger->error.*?\n.*?throw new RagApiException.*?\$e->getCode.*?;',
                re.MULTILINE | re.DOTALL
            )
            matches = pattern.findall(content)
            needs_update = sum(1 for m in matches if 'extractErrorData' not in m)

            if needs_update == 0:
                print(f"  ✓ Already fully updated")
                return False
            print(f"  Found {needs_update} catch blocks still needing update")

        updated_content = update_catch_blocks(content)

        if updated_content == content:
            print(f"  ✗ No changes made")
            return False

        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(updated_content)

        print(f"  ✓ Updated successfully")
        return True

    except Exception as e:
        print(f"  ✗ Error: {e}")
        return False

def main():
    files = [
        'src/Client/RagClient.php',
        'src/Client/AdminClient.php',
        'src/Client/OrganizationClient.php',
    ]

    updated_count = 0
    for filepath in files:
        if process_file(filepath):
            updated_count += 1

    print(f"\n{'='*60}")
    print(f"Updated {updated_count}/{len(files)} files")
    print(f"{'='*60}")

if __name__ == '__main__':
    main()
