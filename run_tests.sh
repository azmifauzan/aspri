#!/bin/bash
python3 -m pip install -r backend/requirements.txt
python3 -m pytest backend/tests/test_document_api.py -s > test_output.log 2>&1
