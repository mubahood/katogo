#!/usr/bin/env python3
"""
Google Drive Refresh Token Generator
This script helps you generate a refresh token for Desktop app OAuth clients.
"""

import json
from google_auth_oauthlib.flow import InstalledAppFlow

# Scopes required for Google Drive access
SCOPES = ['https://www.googleapis.com/auth/drive.file']

def generate_refresh_token(client_secrets_file):
    """Generate refresh token using the client secrets file."""
    
    print("🔐 Google Drive Refresh Token Generator")
    print("=" * 50)
    print()
    print("This will open a browser window for authentication.")
    print("Please sign in with your Google account and grant permissions.")
    print()
    
    # Create the flow using the client secrets file
    flow = InstalledAppFlow.from_client_secrets_file(
        client_secrets_file,
        scopes=SCOPES
    )
    
    # Run the local server flow
    # This will start a local server and open a browser
    credentials = flow.run_local_server(
        port=0,  # Use any available port
        authorization_prompt_message='Please visit this URL: {url}',
        success_message='Authentication successful! You can close this window.',
        open_browser=True
    )
    
    print()
    print("✅ Authentication successful!")
    print("=" * 50)
    print()
    print("📋 Your credentials:")
    print()
    print(f"Client ID:     {credentials.client_id}")
    print(f"Client Secret: {credentials.client_secret}")
    print(f"Refresh Token: {credentials.refresh_token}")
    print()
    print("=" * 50)
    print()
    print("📝 Copy the Refresh Token above and add it to your .env file:")
    print(f"GOOGLE_DRIVE_REFRESH_TOKEN={credentials.refresh_token}")
    print()
    
    return credentials

if __name__ == '__main__':
    import sys
    
    # Path to your downloaded JSON file
    client_secrets_file = '/Users/mac/Downloads/client_secret_1073633720466-sskasa0ucapoa4idc5kp4k4ol3id1ice.apps.googleusercontent.com.json'
    
    try:
        credentials = generate_refresh_token(client_secrets_file)
        print("✅ Success! Use the refresh token shown above.")
    except FileNotFoundError:
        print(f"❌ Error: Could not find file: {client_secrets_file}")
        print("Please make sure the file path is correct.")
        sys.exit(1)
    except Exception as e:
        print(f"❌ Error: {e}")
        sys.exit(1)
