"""Create llm_logs table

Revision ID: a1b2c3d4e5f6
Revises: f1b2c3d4e5f6
Create Date: 2025-08-12 02:13:00.000000

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = 'a1b2c3d4e5f6'
down_revision = 'f1b2c3d4e5f6'
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.create_table('llm_logs',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('user_id', sa.Integer(), nullable=True),
        sa.Column('chat_session_id', sa.Integer(), nullable=True),
        sa.Column('prompt_type', sa.String(length=100), nullable=False),
        sa.Column('prompt_data', sa.JSON(), nullable=False),
        sa.Column('llm_response', sa.Text(), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.ForeignKeyConstraint(['user_id'], ['users.id'], ondelete='SET NULL'),
        sa.ForeignKeyConstraint(['chat_session_id'], ['chat_sessions.id'], ondelete='SET NULL'),
        sa.PrimaryKeyConstraint('id')
    )


def downgrade() -> None:
    op.drop_table('llm_logs')
