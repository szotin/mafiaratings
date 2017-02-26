using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace MafiaRatings.GameObjects
{
    public class GameReader : DataReader
    {
        private int mVersion;

        public GameReader(string data) :
            base(data, ':')
        {
            mVersion = NextInt();
            if (mVersion > Game.CURRENT_LOG_VERSION)
            {
                throw new GameException(Strings.ErrUnsupportedLog);
            }
        }

        public int Version
        {
            get { return mVersion; }
        }
    }

    public class GameWriter : DataWriter
    {
        public GameWriter() :
            base(':')
        {
            Add(Game.CURRENT_LOG_VERSION);
        }
    }

    public enum GameLogRecordType
    {
        Normal = 0,
        MissedSpeech = 1,
        Warning = 2,
        Suicide = 3,
        KickOut = 4
    }

    class GameLogRecord
    {
        private GameLogRecordType mType;
        private int mRound;
        private GameState mGameState;
        private int mPlayerSpeaking;
        private int mCurrentNominant;
        private int mPlayer;

        public GameLogRecord(GameLogRecordType type, int round, GameState gameState, int playerSpeaking, int currentNominant, int player)
        {
            mType = type;
            mRound = round;
            mGameState = gameState;
            mPlayerSpeaking = playerSpeaking;
            mCurrentNominant = currentNominant;
            mPlayer = player;
        }

        public GameLogRecord(GameReader reader)
        {
            mType = (GameLogRecordType) reader.NextInt();
            mRound = reader.NextInt();
            mGameState = (GameState) reader.NextInt();
            mPlayerSpeaking = reader.NextInt();
            mCurrentNominant = reader.NextInt();
            mPlayer = reader.NextInt();
        }

        public void Write(GameWriter writer)
        {
            writer.Add((int)mType);
            writer.Add(mRound);
            writer.Add((int)mGameState);
            writer.Add(mPlayerSpeaking);
            writer.Add(mCurrentNominant);
            writer.Add(mPlayer);
        }

        public GameLogRecordType Type
        {
            get { return mType; }
            set { mType = value; }
        }

        public int Round
        {
            get { return mRound; }
        }

        public GameState GameState
        {
            get { return mGameState; }
        }

        public int PlayerSpeaking
        {
            get { return mPlayerSpeaking; }
        }

        public int CurrentNominant
        {
            get { return mCurrentNominant; }
        }

        public int Player
        {
            get { return mPlayer; }
        }
    }
}
