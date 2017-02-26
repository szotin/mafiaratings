using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.IO;
using System.Diagnostics;

namespace MafiaRatings.GameObjects
{
    public enum GameState
    {
        NotStarted = 0,
        Night0Start = 1,
        Night0Arrange = 2,
        DayStart = 3,
//        DayKilledSpeaking = 4, // deprecated
        DayPlayerSpeaking = 5,
        VotingStart = 6,
        VotingKilledSpeaking = 7,
        Voting = 8,
        VotingMultipleWinners = 9,
        VotingNominantSpeaking = 10,
        NightStart = 11,
        NightShooting = 12,
        NightDonCheckStart = 13,
        NightDonCheck = 14,
        NightSheriffCheckStart = 15,
        NightSheriffCheck = 16,
        MafiaWon = 17,
        CivilWon = 18,
        Terminated = 19,
        DayFreeDiscussion = 20,
    }

    public class Game
    {
        public const int CURRENT_LOG_VERSION = 7;
        public string mDataPath = Database.DataDir + "current_game.txt";

        private static Random mRand = new Random();

        private GamePlayer[] mPlayers = new GamePlayer[10];
        private int mMafiaCount;
        private int mCivilCount;

        private GameState mState;
        private int mRound; // 0 for the first day/night/voting; 1 - for the second day/night/voting; etc
        private int mPlayerSpeaking; // the player currently speaking
        private int mTableOpener; // the player who is speaking first this day
        private int mCurrentNominant; // player nominated during current speech. -1 - if nobody nominated. It is also used during voting the meaning is different: it is current nominant index in Voting.nominants

        private List<GameVoting> mVotings;
        private GameVoting mCurrentVoting;

        private List<Dictionary<int, int>> mShooting;

        private List<GameLogRecord> mLog;

        private int mLang;
        private int mModeratorId = -1;
        private int mClubId;
        private int mPartyId = 0;

        private int mStartTime = 0;
        private int mEndTime = 0;

        private int mBestPlayer = -1; // number from 0 to 9; any value outside this range means "no best player"
        private GameRules mRules;
	
        private event GameActionListener mOnGameAction;

        public delegate void GameActionListener();

        private void AssignRole(GamePlayerRole role)
        {
            while (true)
            {
                int i = mRand.Next(0, 10);
                if (mPlayers[i].Role == GamePlayerRole.Civilian && !mPlayers[i].IsDummy)
                {
                    mPlayers[i].Role = role;
                    return;
                }
            }
        }

        private void OnPlayerStateChange(GamePlayer killedPlayer, GamePlayerKillReason killReason)
        {
            // check if canceling/uncanceling voting needed
            switch (killReason)
            {
                case GamePlayerKillReason.Warnings:
                case GamePlayerKillReason.KickOut:
                case GamePlayerKillReason.Suicide:
                    switch (mState)
                    {
                        case GameState.DayStart:
                        case GameState.DayPlayerSpeaking:
                        case GameState.Voting:
                        case GameState.VotingNominantSpeaking:
                        case GameState.VotingStart:
                        case GameState.VotingMultipleWinners:
                            mCurrentVoting.Canceled = killedPlayer.IsDead;
                            break;
                    }
                    break;
            }

            // update player counts
            switch (killedPlayer.Role)
            {
                case GamePlayerRole.Civilian:
                case GamePlayerRole.Sheriff:
                    if (killedPlayer.IsDead)
                    {
                        --mCivilCount;
                    }
                    else
                    {
                        ++mCivilCount;
                    }
                    break;
                case GamePlayerRole.Mafia:
                case GamePlayerRole.Don:
                    if (killedPlayer.IsDead)
                    {
                        --mMafiaCount;
                    }
                    else
                    {
                        ++mMafiaCount;
                    }
                    break;
            }

            if (killedPlayer.IsDead)
            {
                // check if the game is over
                if (mMafiaCount <= 0)
                {
                    mState = GameState.CivilWon;
                }
                else if (mCivilCount <= mMafiaCount)
                {
                    mState = GameState.MafiaWon;
                }
            }
        }

        private int WhoMurdered()
        {
            while (mShooting.Count <= mRound)
            {
                mShooting.Add(new Dictionary<int, int>(3));
            }
            Dictionary<int, int> shots = mShooting[mRound];

            int playerNum = GetDummyPlayerIndex();
            if (playerNum >= 0)
            {
                foreach (GamePlayer p in mPlayers)
                {
                    if (p.Role == GamePlayerRole.Mafia || p.Role == GamePlayerRole.Don)
                    {
                        shots[p.Number] = playerNum;
                    }
                }
            }
            else
            {
                foreach (int pNum in shots.Values)
                {
                    if (pNum < 0)
                    {
                        return -1;
                    }

                    if (playerNum < 0)
                    {
                        playerNum = pNum;
                    }
                    else if (playerNum != pNum)
                    {
                        return -1;
                    }
                }
            }
            return playerNum;
        }

        public Game()
        {
            mClubId = Database.Club.Id;
            mState = GameState.NotStarted;
            if (File.Exists(mDataPath))
            {
                string log = File.ReadAllText(mDataPath);
                GameReader reader = new GameReader(log);
			    if (reader.Version > 6)
			    {
                    mRules = Database.Rules[reader.NextInt()];
			    }
			    else
			    {
                    mRules = Database.Rules[Database.Club.RulesId];
			    }
                
                mClubId = reader.NextInt();
                mPartyId = reader.NextInt();
                reader.NextInt(); // ignore user_id. May be we should reject if it's different from the current one..
                mModeratorId = reader.NextInt();
                mLang = reader.NextInt();
                mStartTime = reader.NextInt();
                mEndTime = mStartTime + reader.NextInt();
                if (reader.Version > 4)
                {
                    mBestPlayer = reader.NextInt();
                }

                mMafiaCount = mCivilCount = 0;
                for (int i = 0; i < 10; ++i)
                {
                    GamePlayer player = new GamePlayer(i, reader, OnPlayerStateChange);
                    mPlayers[i] = player;

                    if (player.IsAlive)
                    {
                        switch (player.Role)
                        {
                            case GamePlayerRole.Civilian:
                            case GamePlayerRole.Sheriff:
                                ++mCivilCount;
                                break;
                            case GamePlayerRole.Mafia:
                            case GamePlayerRole.Don:
                                ++mMafiaCount;
                                break;
                        }
                    }
                }

                mState = (GameState)reader.NextInt();
                mRound = reader.NextInt();
                mPlayerSpeaking = reader.NextInt();
                mTableOpener = reader.NextInt();
                mCurrentNominant = reader.NextInt();

                int votingsCount = reader.NextInt();
                mVotings = new List<GameVoting>(votingsCount);
                for (int i = 0; i < votingsCount; ++i)
                {
                    CurrentVoting = new GameVoting(this, reader);
                }

                int shootingCount = reader.NextInt();
                mShooting = new List<Dictionary<int, int>>(shootingCount);
                for (int i = 0; i < shootingCount; ++i)
                {
                    Dictionary<int, int> shots = new Dictionary<int, int>(3);
                    int shotsCount = reader.NextInt();
                    for (int j = 0; j < shotsCount; ++j)
                    {
                        int key = reader.NextInt();
                        shots[key] = reader.NextInt();
                    }
                    mShooting.Add(shots);
                }

                int logCount = reader.NextInt();
                mLog = new List<GameLogRecord>(logCount);
                for (int i = 0; i < logCount; ++i)
                {
                    mLog.Add(new GameLogRecord(reader));
                }
            }
            else
            {
                for (int i = 0; i < 10; ++i)
                {
                    mPlayers[i] = new GamePlayer(i, OnPlayerStateChange);
                }
                mMafiaCount = 3;
                mCivilCount = 7;
            }
        }

        private string GenerateTextLog()
        {
            GameWriter writer = new GameWriter();

            writer.Add(mRules.Id);
            writer.Add(mClubId);
            writer.Add(mPartyId);
            writer.Add(Database.UserId);
            writer.Add(mModeratorId);
            writer.Add(mLang);
            writer.Add(mStartTime);
            writer.Add(mEndTime - mStartTime);
            writer.Add(mBestPlayer);

            for (int i = 0; i < 10; ++i)
            {
                mPlayers[i].Write(writer);
            }

            writer.Add((int)mState);
            writer.Add(mRound);
            writer.Add(mPlayerSpeaking);
            writer.Add(mTableOpener);
            writer.Add(mCurrentNominant);

            writer.Add(mVotings.Count);
            for (int i = 0; i < mVotings.Count; ++i)
            {
                mVotings[i].Write(writer);
            }

            writer.Add(mShooting.Count);
            for (int i = 0; i < mShooting.Count; ++i)
            {
                Dictionary<int, int> shooting = mShooting[i];
                writer.Add(shooting.Count);
                foreach (KeyValuePair<int, int> pair in shooting)
                {
                    writer.Add(pair.Key);
                    writer.Add(pair.Value);
                }
            }

            writer.Add(mLog.Count);
            for (int i = 0; i < mLog.Count; ++i)
            {
                mLog[i].Write(writer);
            }

            return writer.Data;
        }

        private void Save(bool updateTime = true)
        {
            if (updateTime)
            {
                mEndTime = Timestamp.Now;
            }
            File.WriteAllText(mDataPath, GenerateTextLog());
            mOnGameAction();
        }

        public void SetPlayer(int playerNum, Registration reg)
        {
            if (mState != GameState.NotStarted)
            {
                throw new GameException(Strings.ErrGameStarted);
            }

            if (playerNum < 0 || playerNum >= 10)
            {
                throw new GameException(Strings.ErrPlayerNum);
            }

            if (reg != null && reg.PartyId != mPartyId)
            {
                throw new GameException(Strings.ErrAnotherParty);
            }

            GamePlayer player = mPlayers[playerNum];
            if (reg != null && player.UserId != reg.UserId)
            {
                if (mModeratorId == reg.UserId)
                {
                    Party party = Database.Parties[mPartyId];
                    if (party.AllCanModerate)
                    {
                        mModeratorId = -1;
                    }
                    else
                    {
                        throw new GameException(string.Format(Strings.ErrPlayerModerator, reg.Nickname));
                    }
                }

                for (int i = 0; i < 10; ++i)
                {
                    if (i != playerNum && mPlayers[i].UserId == reg.UserId)
                    {
                        mPlayers[i].SetUser(null);
                    }
                }
            }
            player.SetUser(reg);

            mOnGameAction();
        }

        public bool BackEnabled
        {
            get { return mLog != null && mLog.Count > 1; }
        }

        public bool NextEnabled
        {
            get
            {
                if (mState == GameState.NotStarted)
                {
                    if (!Language.IsValidLang(mLang) || mModeratorId <= 0 || !Database.Parties.Contains(mPartyId) || mClubId <= 0 || mRules == null)
                    {
                        return false;
                    }

                    for (int i = 0; i < 10; ++i)
                    {
                        if (!mPlayers[i].Initialized)
                        {
                            return false;
                        }
                    }
                }
                return true;
            }
        }

        public bool TerminateEnabled
        {
            get
            {
                switch (mState)
                {
                    case GameState.CivilWon:
                    case GameState.MafiaWon:
                    case GameState.Terminated:
                    case GameState.NotStarted:
                        return false;
                }
                return true;
            }
        }

        public bool IsNight
        {
            get
            {
                switch (mState)
                {
                    case GameState.Night0Start:
                    case GameState.Night0Arrange:
                    case GameState.NightStart:
                    case GameState.NightShooting:
                    case GameState.NightDonCheckStart:
                    case GameState.NightDonCheck:
                    case GameState.NightSheriffCheckStart:
                    case GameState.NightSheriffCheck:
                        return true;
                }
                return false;
            }
        }

        public GameVoting CurrentVoting
        {
            get { return mCurrentVoting; }
            private set
            {
                mCurrentVoting = value;
                mVotings.Add(value);
            }
        }

        public int NextPlayer(int playerNum)
        {
            if (playerNum < 0)
            {
                playerNum = mTableOpener;
                GamePlayer player = mPlayers[playerNum];
                while (player.IsDead || player.IsDummy)
                {
                    ++playerNum;
                    if (playerNum >= 10)
                    {
                        playerNum = 0;
                    }
                    player = mPlayers[playerNum];
                }
                return playerNum;
            }

            while (true)
            {
                ++playerNum;
                if (playerNum >= 10)
                {
                    playerNum = 0;
                }

                if (playerNum == mTableOpener)
                {
                    return -1;
                }

                GamePlayer player = mPlayers[playerNum];
                if (player.IsAlive && !player.IsDummy)
                {
                    return playerNum;
                }
            }
        }
	
        public int PrevPlayer(int playerNum)
        {
            if (playerNum < 0)
            {
                playerNum = mTableOpener;
            }
		
            while (true)
            {
                --playerNum;
                if (playerNum < 0)
                {
                    playerNum = 9;
                }

                GamePlayer player = mPlayers[playerNum];
                if (player.IsAlive && !player.IsDummy)
                {
                    return playerNum;
                }
            }
        }

        private void Vote(int playerNum, int nominant)
        {
            GamePlayer player = mPlayers[playerNum];
            if (player.IsAlive && !player.IsDummy)
            {
                mCurrentVoting.Vote(playerNum, nominant);
            }
        }

        public void Vote(int playerNum, bool vote)
        {
            if (vote)
            {
                Vote(playerNum, mCurrentNominant);
            }
            else
            {
                Vote(playerNum, -1);
            }
        }

        public bool DonCanCheck
        {
            get
            {
                foreach (GamePlayer player in mPlayers)
                {
                    if (player.Role == GamePlayerRole.Don)
                    {
				        if (player.State == GamePlayerState.Alive)
				        {
					        return true;
				        }
				        else if (
					        player.State == GamePlayerState.KilledNight &&
					        player.KillRound == mRound &&
					        player.KillReason == GamePlayerKillReason.Normal)
				        {
					        return true;
				        }
				        break;
                    }
                }
                return false;
            }
        }

        public bool SheriffCanCheck
        {
            get
            {
                foreach (GamePlayer player in mPlayers)
                {
                    if (player.Role == GamePlayerRole.Sheriff)
                    {
                        if (player.State == GamePlayerState.Alive)
                        {
                            return true;
                        }
                        else if (
                            player.State == GamePlayerState.KilledNight &&
                            player.KillRound == mRound &&
                            player.KillReason == GamePlayerKillReason.Normal)
                        {
                            return true;
                        }
                        break;
                    }
                }
                return false;
            }
        }

        public void SetPlayerRole(int playerNum, GamePlayerRole role)
        {
            if (mState != GameState.Night0Start)
            {
                return;
            }

            GamePlayer player = mPlayers[playerNum];
            if (role == player.Role)
            {
                return;
            }

            for (int i = 9; i >= 0; --i)
            {
                GamePlayer p = mPlayers[i];
                if (p.Role == role)
                {
                    p.Role = player.Role;
                    break;
                }
            }
            player.Role = role;
        }

        public bool KillingThisDay
        {
            get { return mRound > 0 || !mRules.Day1NoKill; }
        }

        public bool DefenciveRoundThisDay
        {
            get { return mRules.DefensiveRound && KillingThisDay; }
        }

        public void Next()
        {
            GameLogRecord logRec = new GameLogRecord(GameLogRecordType.Normal, mRound, mState, mPlayerSpeaking, mCurrentNominant, -1);

            switch (mState)
            {
                case GameState.NotStarted:
                    if (!NextEnabled)
                    {
                        throw new GameException(Strings.ErrGameNotReady);
                    }

                    AssignRole(GamePlayerRole.Don);
                    AssignRole(GamePlayerRole.Mafia);
                    AssignRole(GamePlayerRole.Mafia);
                    AssignRole(GamePlayerRole.Sheriff);

                    mState = GameState.Night0Start;
                    mRound = 0;
                    mPlayerSpeaking = -1;
                    mCurrentNominant = -1;
                    mTableOpener = 0;

                    mVotings = new List<GameVoting>();
                    CurrentVoting = new GameVoting(this, 0);

                    mShooting = new List<Dictionary<int,int>>();
                    mLog = new List<GameLogRecord>();

                    mStartTime = Timestamp.Now;
                    break;

                case GameState.Night0Start:
                    mState = GameState.Night0Arrange;
                    break;

                case GameState.Night0Arrange:
                    mState = GameState.DayStart;
                    break;

                case GameState.DayStart:
                    if (mCurrentNominant >= 0)
                    {
                        mCurrentVoting.AddNominant(mCurrentNominant, mPlayerSpeaking);
                        mCurrentNominant = -1;
                    }
                    if (mRules.FreeRound)
                    {
                        mState = GameState.DayFreeDiscussion;
                        mPlayerSpeaking = -1;
                    }
                    else
                    {
                        mState = GameState.DayPlayerSpeaking;
                        mPlayerSpeaking = NextPlayer(-1);
                    }
                    break;

                case GameState.DayFreeDiscussion:
                    mState = GameState.DayPlayerSpeaking;
                    mPlayerSpeaking = NextPlayer(-1);
                    break;

                case GameState.DayPlayerSpeaking:
                    if (mPlayers[mPlayerSpeaking].MissesSpeech)
                    {
                        logRec.Type = GameLogRecordType.MissedSpeech;
                        mPlayers[mPlayerSpeaking].MissesSpeech = false;
                    }

                    if (mCurrentNominant >= 0)
                    {
                        mCurrentVoting.AddNominant(mCurrentNominant, mPlayerSpeaking);
                    }

                    mCurrentNominant = -1;
                    mPlayerSpeaking = NextPlayer(mPlayerSpeaking);
                    if (mPlayerSpeaking < 0)
                    {
                        mState = GameState.VotingStart;
                    }
                    break;

                case GameState.VotingStart:
                    if (mCurrentVoting.Canceled)
                    {
                        mState = GameState.NightStart;
                        CurrentVoting = new GameVoting(this, mRound + 1);
                    }
                    else switch(mCurrentVoting.Nominants.Count)
                    {
                        case 0:
                            mState = GameState.NightStart;
                            CurrentVoting = new GameVoting(this, mRound + 1);
                            break;

                        case 1:
                            mState = GameState.VotingKilledSpeaking;
                            mCurrentNominant = 0;
                            mPlayerSpeaking = mCurrentVoting.Winners[0];
                            if (KillingThisDay)
                            {
                                mPlayers[mPlayerSpeaking].Kill(false, GamePlayerKillReason.Normal, mRound);
                            }
                            break;

                        default:
                            if (DefenciveRoundThisDay)
						    {
                                mState = GameState.VotingNominantSpeaking;
						    }
						    else
						    {
                                mState = GameState.Voting;
    					    }
                            mCurrentNominant = 0;
                            mPlayerSpeaking = mCurrentVoting.Nominants[0].PlayerNum;
                            break;
                    }
                    break;

                case GameState.VotingKilledSpeaking:
                    if (mCurrentNominant < mCurrentVoting.Winners.Count - 1)
                    {
                        ++mCurrentNominant;
                        mPlayerSpeaking = mCurrentVoting.Winners[mCurrentNominant];
                    }
                    else
                    {
                        mState = GameState.NightStart;
                        CurrentVoting = new GameVoting(this, mRound + 1);
                        mPlayerSpeaking = -1;
                        mCurrentNominant = -1;
                    }
                    break;

                case GameState.Voting:
                    if (mCurrentVoting.Canceled)
                    {
                        mState = GameState.NightStart;
                        CurrentVoting = new GameVoting(this, mRound + 1);
                        mPlayerSpeaking = -1;
                        mCurrentNominant = -1;
                    }
                    else if (mCurrentNominant < mCurrentVoting.Nominants.Count - 2)
                    {
                        mPlayerSpeaking = mCurrentVoting.Nominants[++mCurrentNominant].PlayerNum;
                    }
                    else if (mCurrentVoting.Winners.Count == 1)
                    {
                        mState = GameState.VotingKilledSpeaking;
                        mCurrentNominant = 0;
                        mPlayerSpeaking = mCurrentVoting.Winners[0];
                        if (KillingThisDay)
                        {
                            mPlayers[mPlayerSpeaking].Kill(false, GamePlayerKillReason.Normal, mRound);
                        }
                    }
                    else
                    {
                        mState = GameState.VotingMultipleWinners;
                        mCurrentNominant = -1;
                        mPlayerSpeaking = -1;
                    }
                    break;

                case GameState.VotingMultipleWinners:
                    Debug.Assert(mCurrentVoting.Winners.Count > 1);
                    if (mCurrentVoting.VotingRound == 0)
                    {
                        if (!KillingThisDay)
                        {
                            // round0 - nobody is killed they all are speaking
                            mState = GameState.VotingKilledSpeaking;
                            mCurrentNominant = 0;
                            mPlayerSpeaking = mCurrentVoting.Winners[0];
                        }
                        else if (mCurrentVoting.Canceled || (NumPlayers == 4 && mRules.NoCrash4))
                        {
                            // A special case: 4 players, multiple winners - no second voting. Nobody is killed.
                            mState = GameState.NightStart;
                            CurrentVoting = new GameVoting(this, mRound + 1);
                            mPlayerSpeaking = -1;
                            mCurrentNominant = -1;
                        }
                        else
                        {
                            // vote again
                            mState = GameState.VotingNominantSpeaking;
                            CurrentVoting = new GameVoting(this, mCurrentVoting);
                            mCurrentNominant = 0;
                            mPlayerSpeaking = mCurrentVoting.Nominants[0].PlayerNum;
                        }
                    }
                    else if (!mCurrentVoting.MultipleKill || NumPlayers == 3)
                    {
                        // 3 players is a special case. They can't be all killed, so we ignore multiple_kill flag.
                        mState = GameState.NightStart;
                        CurrentVoting = new GameVoting(this, mRound + 1);
                    }
                    else
                    {
                        mState = GameState.VotingKilledSpeaking;
                        mCurrentNominant = 0;
                        mPlayerSpeaking = mCurrentVoting.Winners[0];
                        foreach (int playerNum in mCurrentVoting.Winners)
                        {
                            mPlayers[playerNum].Kill(false, GamePlayerKillReason.Normal, mRound);
                        }
                    }
                    break;

                case GameState.VotingNominantSpeaking:
                    if (mCurrentVoting.Canceled)
                    {
                        mState = GameState.NightStart;
                        CurrentVoting = new GameVoting(this, mRound + 1);
                    }
                    else
                    {
                        ++mCurrentNominant;
                        if (mCurrentNominant < mCurrentVoting.Nominants.Count)
                        {
                            mPlayerSpeaking = mCurrentVoting.Nominants[mCurrentNominant].PlayerNum;
                        }
                        else
                        {
                            mState = GameState.Voting;
                            mCurrentNominant = 0;
                            mPlayerSpeaking = -1;
                        }
                    }
                    break;

                case GameState.NightStart:
                    mState = GameState.NightShooting;
                    break;

                case GameState.NightShooting:
                    mPlayerSpeaking = WhoMurdered();
                    mState = GameState.NightDonCheckStart;
                    if (mPlayerSpeaking >= 0)
                    {
                        mPlayers[mPlayerSpeaking].Kill(true, GamePlayerKillReason.Normal, mRound);
                    }
                    break;

                case GameState.NightDonCheckStart:
                    if (mCurrentNominant >= 0 && DonCanCheck)
                    {
                        mState = GameState.NightDonCheck;
                    }
                    else
                    {
                        mState = GameState.NightSheriffCheckStart;
                    }
                    break;

                case GameState.NightDonCheck:
                    mState = GameState.NightSheriffCheckStart;
                    break;

                case GameState.NightSheriffCheckStart:
                    if (mCurrentNominant >= 0 && SheriffCanCheck)
                    {
                        mState = GameState.NightSheriffCheck;
                    }
                    else
                    {
                        mState = GameState.DayStart;
                        ++mRound;
                        mCurrentNominant = -1;
                        mTableOpener = NextPlayer(mTableOpener);
                    }
                    break;

                case GameState.NightSheriffCheck:
                    mState = GameState.DayStart;
                    ++mRound;
                    mCurrentNominant = -1;
                    mTableOpener = NextPlayer(mTableOpener);
                    break;

                case GameState.MafiaWon:
                case GameState.CivilWon:
                case GameState.Terminated:
                    Submit();
                    return;
            }

            mLog.Add(logRec);
            Save();
        }

        public void WarnPlayer(int playerNum)
        {
            switch (mState)
            {
                case GameState.MafiaWon:
                case GameState.CivilWon:
                case GameState.Terminated:
                    return;
            }

            mLog.Add(new GameLogRecord(GameLogRecordType.Warning, mRound, mState, mPlayerSpeaking, mCurrentNominant, playerNum));
            mPlayers[playerNum].Warn(IsNight, mRound);
            Save();
        }

        public bool CanToggleSheriffStatus
        {
            get
            {
                switch (mState)
                {
                    case GameState.NotStarted:
                    case GameState.Night0Start:
                    case GameState.Night0Arrange:
                    case GameState.NightStart:
                    case GameState.NightShooting:
                    case GameState.NightDonCheckStart:
                    case GameState.NightDonCheck:
                    case GameState.NightSheriffCheckStart:
                    case GameState.NightSheriffCheck:
                    case GameState.MafiaWon:
                    case GameState.CivilWon:
                    case GameState.Terminated:
                        return false;
                }
                return true;
            }
        }
	
        public void NominatePlayer(int playerNum)
        {
            if (mState != GameState.DayPlayerSpeaking && (mState != GameState.DayStart || !mRules.NightKillCanNominate))
            {
                return;
            }

            if (playerNum < 0)
            {
                return;
            }

            if (mPlayers[playerNum].IsDead)
            {
                return;
            }
            mCurrentNominant = playerNum;
        }

        public void Suicide(int playerNum)
        {
            switch (mState)
            {
                case GameState.MafiaWon:
                case GameState.CivilWon:
                case GameState.Terminated:
                    return;
            }

            mLog.Add(new GameLogRecord(GameLogRecordType.Suicide, mRound, mState, mPlayerSpeaking, mCurrentNominant, playerNum));
            mPlayers[playerNum].Kill(IsNight, GamePlayerKillReason.Suicide, mRound);
            Save();
        }

        public void KickOut(int playerNum)
        {
            switch (mState)
            {
                case GameState.MafiaWon:
                case GameState.CivilWon:
                case GameState.Terminated:
                    return;
            }

            mLog.Add(new GameLogRecord(GameLogRecordType.KickOut, mRound, mState, mPlayerSpeaking, mCurrentNominant, playerNum));
            mPlayers[playerNum].Kill(IsNight, GamePlayerKillReason.KickOut, mRound);
            Save();
        }

        public void DonChecks(int playerNum)
        {
            if (mState != GameState.NightDonCheckStart)
            {
                return;
            }
	
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = mPlayers[i];
                if (player.DonCheck == mRound)
                {
                    player.DonCheck = -1;
                }
            }
		
            if (playerNum >= 0)
            {
                mPlayers[playerNum].DonCheck = mRound;
            }
            mCurrentNominant = playerNum;
        }

        public void SheriffChecks(int playerNum)
        {
            if (mState != GameState.NightSheriffCheckStart)
            {
                return;
            }
	
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = mPlayers[i];
                if (player.SheriffCheck == mRound)
                {
                    player.SheriffCheck = -1;
                }
            }
		
            if (playerNum >= 0)
            {
                mPlayers[playerNum].SheriffCheck = mRound;
            }
            mCurrentNominant = playerNum;
        }

        public void ArrangePlayer(int playerNum, int round)
        {
            if (mState != GameState.Night0Arrange)
            {
                return;
            }

            GamePlayer player = mPlayers[playerNum];
            if (player.IsDummy)
            {
                return;
            }

            if (round >= 0)
            {
                for (int i = 0; i < 10; ++i)
                {
                    if (i == playerNum)
                    {
                        continue;
                    }

                    GamePlayer p = mPlayers[i];
                    if (p.Arranged == round)
                    {
                        if (p.IsDummy)
                        {
                            return;
                        }
                        p.Arranged = -1;
                    }
                }
            }
            player.Arranged = round;
        }

        public void NightShot(int shooterNum, int playerNum)
        {
            if (mState != GameState.NightShooting)
            {
                return;
            }

            while (mShooting.Count <= mRound)
            {
                mShooting.Add(new Dictionary<int,int>(3));
            }
            mShooting[mRound][shooterNum] = playerNum;
        }

        public int GetNightShot(int shooterNum)
        {
            if (mRound < mShooting.Count && mShooting[mRound].ContainsKey(shooterNum))
            {
                return mShooting[mRound][shooterNum];
            }
            return -1;
        }

        public void Terminate()
        {
            mLog.Add(new GameLogRecord(GameLogRecordType.Normal, mRound, mState, mPlayerSpeaking, mCurrentNominant, -1));
            mState = GameState.Terminated;
            Save();
        }

        public void VotingKillAll(bool killAll)
        {
            if (mState == GameState.VotingMultipleWinners)
            {
                mCurrentVoting.MultipleKill = killAll;
            }
        }

        private static void BrokenLog(GameState from, GameState to)
        {
            Debug.Fail(String.Format(Strings.ErrBrokenLog, from, to));
        }

        public void Back()
        {
            int logNum = mLog.Count - 1;
            if (logNum < 0)
            {
                return;
            }

            GameLogRecord logRec = mLog[logNum];

            if (logRec.Type == GameLogRecordType.Warning)
            {
                mPlayers[logRec.Player].Unwarn();
            }
            else if (logRec.Type == GameLogRecordType.Suicide || logRec.Type == GameLogRecordType.KickOut)
            {
                mPlayers[logRec.Player].Resurrect();
            }
            else if (mState != GameState.Terminated)
            {
                switch (logRec.GameState)
                {
                    case GameState.DayStart:
                        switch (mState)
                        {
                            case GameState.DayPlayerSpeaking:
							    if (mRules.FreeRound)
							    {
                                    BrokenLog(mState, logRec.GameState);
							    }
                                if (logRec.CurrentNominant >= 0 && mCurrentVoting.Nominants.Count > 0)
                                {
                                    mCurrentVoting.RemoveNominant(mCurrentVoting.Nominants.Count - 1);
                                }
                                break;
						    case GameState.DayFreeDiscussion:
							    if (!mRules.FreeRound)
							    {
                                    BrokenLog(mState, logRec.GameState);
							    }
                                if (logRec.CurrentNominant >= 0 && mCurrentVoting.Nominants.Count > 0)
                                {
                                    mCurrentVoting.RemoveNominant(mCurrentVoting.Nominants.Count - 1);
                                }
							    break;
                            default:
                                BrokenLog(mState, logRec.GameState);
                                break;
                        }
                        break;

				    case GameState.DayFreeDiscussion:
					    if (mState != GameState.DayPlayerSpeaking || !mRules.FreeRound)
					    {
                            BrokenLog(mState, logRec.GameState);
					    }
                        if (logRec.CurrentNominant >= 0 && mCurrentVoting.Nominants.Count > 0)
                        {
                            mCurrentVoting.RemoveNominant(mCurrentVoting.Nominants.Count - 1);
                        }
					    break;

                    case GameState.DayPlayerSpeaking:
                        switch (mState)
                        {
                            case GameState.DayPlayerSpeaking:
                            case GameState.VotingStart:
                                if (logRec.Type == GameLogRecordType.MissedSpeech)
                                {
                                    mPlayers[logRec.PlayerSpeaking].MissesSpeech = true;
                                }
                                if (logRec.CurrentNominant >= 0 && mCurrentVoting.Nominants.Count > 0)
                                {
                                    mCurrentVoting.RemoveNominant(mCurrentVoting.Nominants.Count - 1);
                                }
                                break;
                            default:
                                BrokenLog(mState, logRec.GameState);
                                break;
                        }
                        break;

                    case GameState.VotingStart:
                        switch (mState)
                        {
                            case GameState.NightStart:
                                if (mVotings.Count > 1)
                                {
                                    mVotings.RemoveAt(mVotings.Count - 1);
                                    mCurrentVoting = mVotings[mVotings.Count - 1];
                                }
                                break;
                            case GameState.VotingKilledSpeaking:
                            case GameState.MafiaWon:
                            case GameState.CivilWon:
                                if (KillingThisDay)
                                {
                                    Debug.Assert(mCurrentVoting.Winners.Count == 1);
                                    mPlayers[mCurrentVoting.Winners[0]].Resurrect();
                                }
                                break;
                            case GameState.Voting:
                                if (DefenciveRoundThisDay)
							    {
                                    BrokenLog(mState, logRec.GameState);
							    }
                                break;
                            case GameState.VotingNominantSpeaking:
                                if (!DefenciveRoundThisDay)
							    {
                                    BrokenLog(mState, logRec.GameState);
							    }
                                break;
                            default:
                                BrokenLog(mState, logRec.GameState);
                                break;
                        }
                        break;

                    case GameState.VotingKilledSpeaking:
                        switch (mState)
                        {
                            case GameState.NightStart:
                                if (mVotings.Count > 1)
                                {
                                    mVotings.RemoveAt(mVotings.Count - 1);
                                    mCurrentVoting = mVotings[mVotings.Count - 1];
                                }
                                break;
                            case GameState.VotingKilledSpeaking:
                                break;
                            default:
                                BrokenLog(mState, logRec.GameState);
                                break;
                        }
                        break;

                    case GameState.Voting:
                        switch (mState)
                        {
                            case GameState.NightStart:
                                if (mVotings.Count > 1)
                                {
                                    mVotings.RemoveAt(mVotings.Count - 1);
                                    mCurrentVoting = mVotings[mVotings.Count - 1];
                                }
                                break;
                            case GameState.VotingKilledSpeaking:
                            case GameState.MafiaWon:
                            case GameState.CivilWon:
                                if (KillingThisDay && mCurrentVoting.Winners != null)
                                {
                                    Debug.Assert(mCurrentVoting.Winners.Count == 1);
                                    mPlayers[mCurrentVoting.Winners[0]].Resurrect();
                                }
                                break;
                            case GameState.Voting:
                            case GameState.VotingMultipleWinners:
                                break;
                            default:
                                BrokenLog(mState, logRec.GameState);
                                break;
                        }
                        break;

                    case GameState.VotingMultipleWinners:
                        switch (mState)
                        {
                            case GameState.NightStart:
                            case GameState.VotingNominantSpeaking:
                                if (mVotings.Count > 1)
                                {
                                    mVotings.RemoveAt(mVotings.Count - 1);
                                    mCurrentVoting = mVotings[mVotings.Count - 1];
                                }
                                break;
                            case GameState.VotingKilledSpeaking:
                            case GameState.MafiaWon:
                            case GameState.CivilWon:
                                Debug.Assert(!mCurrentVoting.Canceled);
                                if (KillingThisDay)
                                {
                                    Debug.Assert(mCurrentVoting.Winners.Count > 1);
                                    foreach (int winner in mCurrentVoting.Winners)
                                    {
                                        mPlayers[winner].Resurrect();
                                    }
                                }
                                break;
                            default:
                                BrokenLog(mState, logRec.GameState);
                                break;
                        }
                        break;

                    case GameState.VotingNominantSpeaking:
                        switch (mState)
                        {
                            case GameState.NightStart:
                                if (mVotings.Count > 1)
                                {
                                    mVotings.RemoveAt(mVotings.Count - 1);
                                    mCurrentVoting = mVotings[mVotings.Count - 1];
                                }
                                break;
                            case GameState.VotingNominantSpeaking:
                            case GameState.Voting:
                                break;
                            default:
                                BrokenLog(mState, logRec.GameState);
                                break;
                        }
                        break;

                    case GameState.NightShooting:
                        switch (mState)
                        {
                            case GameState.NightDonCheckStart:
                            case GameState.MafiaWon:
                            case GameState.CivilWon:
                                if (mPlayerSpeaking >= 0)
                                {
                                    mPlayers[mPlayerSpeaking].Resurrect();
                                }
                                for (int i = 0; i < 10; ++i)
                                {
                                    if (mPlayers[i].DonCheck == mRound)
                                    {
                                        mPlayers[i].DonCheck = -1;
                                    }
                                }
                                break;
                            default:
                                BrokenLog(mState, logRec.GameState);
                                break;
                        }
                        break;
                
                    case GameState.NightDonCheckStart:
                        switch (mState)
                        {
                            case GameState.NightSheriffCheckStart:
                                for (int i = 0; i < 10; ++i)
                                {
                                    if (mPlayers[i].SheriffCheck == mRound)
                                    {
                                        mPlayers[i].SheriffCheck = -1;
                                    }
                                }
                                break;
                            case GameState.NightDonCheck:
                                break;
                            default:
                                BrokenLog(mState, logRec.GameState);
                                break;
                        }
                        break;

                    case GameState.NightDonCheck:
                        switch (mState)
                        {
                            case GameState.NightSheriffCheckStart:
                                for (int i = 0; i < 10; ++i)
                                {
                                    if (mPlayers[i].SheriffCheck == mRound)
                                    {
                                        mPlayers[i].SheriffCheck = -1;
                                    }
                                }
                                break;
                            default:
                                BrokenLog(mState, logRec.GameState);
                                break;
                        }
                        break;
					
                    case GameState.NightSheriffCheckStart:
                        switch (mState)
                        {
                            case GameState.DayStart:
                                mTableOpener = PrevPlayer(mTableOpener);
                                break;
                            case GameState.NightSheriffCheck:
                                break;
                            default:
                                BrokenLog(mState, logRec.GameState);
                                break;
                        }
                        break;

                    case GameState.NightSheriffCheck:
                        switch (mState)
                        {
                            case GameState.DayStart:
                                mTableOpener = PrevPlayer(mTableOpener);
                                break;
                            default:
                                BrokenLog(mState, logRec.GameState);
                                break;
                        }
                        break;
                }

                if (mState == GameState.NightShooting)
                {
                    while (mShooting.Count > mRound)
                    {
                        mShooting.RemoveAt(mShooting.Count - 1);
                    }
                }
            }

            mRound = logRec.Round;
            mState = logRec.GameState;
            mPlayerSpeaking = logRec.PlayerSpeaking;
            mCurrentNominant = logRec.CurrentNominant;
            mLog.RemoveAt(logNum);

            Save();
        }

        public int GetRating(int playerNum)
        {
            int rating = 0;
            if (mState == GameState.MafiaWon)
            {
                GamePlayer player = mPlayers[playerNum];
                switch (player.Role)
                {
                    case GamePlayerRole.Sheriff:
                        rating = -1;
                        break;
                    case GamePlayerRole.Mafia:
                        rating = 4;
                        break;
                    case GamePlayerRole.Don:
                        rating = 5;
                        break;
                }
            }
            else if (mState == GameState.CivilWon)
            {
                GamePlayer player = mPlayers[playerNum];
                switch (player.Role)
                {
                    case GamePlayerRole.Civilian:
                        rating = 3;
                        break;
                    case GamePlayerRole.Sheriff:
                        rating = 4;
                        break;
                    case GamePlayerRole.Don:
                        rating = -1;
                        break;
                }
            }
            if (playerNum == mBestPlayer)
            {
                ++rating;
            }
            return rating;
        }

        public string PlayerPrintableName(int playerNum)
        {
            return (playerNum + 1).ToString() + " - " + mPlayers[playerNum].Name;
        }

        public GameState State
        {
            get { return mState; }
        }

        public void Reset()
        {
            if (mState != GameState.NotStarted)
            {
                throw new GameException(Strings.ErrGameStarted);
            }

            for (int i = 0; i < 10; ++i)
            {
                mPlayers[i].SetUser(null);
            }

            Party party = Database.Parties[mPartyId];
            if (party == null || party.AllCanModerate)
            {
                ModeratorId = -1;
            }
            mOnGameAction();
        }

        public int Lang
        {
            get { return mLang; }
            set
            {
                if (mState != GameState.NotStarted)
                {
                    throw new GameException(Strings.ErrGameStarted);
                }
                if (mLang != value)
                {
                    mLang = value;
                    mOnGameAction();
                }
            }
        }

        public GamePlayer[] Players
        {
            get { return mPlayers; }
        }

        public int ModeratorId
        {
            get { return mModeratorId; }
            set
            {
                if (mState != GameState.NotStarted)
                {
                    throw new GameException(Strings.ErrGameStarted);
                }

                Party party = Database.Parties[mPartyId];
                if (party == null || party.AllCanModerate)
                {
                    if (mModeratorId != value)
                    {
                        if (value > 0)
                        {
                            for (int i = 0; i < 10; ++i)
                            {
                                if (mPlayers[i].UserId == value)
                                {
                                    mPlayers[i].SetUser(null);
                                }
                            }
                        }
                        mModeratorId = value;
                        mOnGameAction();
                    }
                }
                else
                {
                    mModeratorId = Database.UserId;
                }
            }
        }

        public void SetModerator(Registration reg)
        {
            Party party = Database.Parties[mPartyId];
            if (party == null || party.AllCanModerate)
            {
                if (reg != null)
                {
                    ModeratorId = reg.UserId;
                }
                else
                {
                    ModeratorId = -1;
                }
            }
        }

        public GamePlayer GetDummyPlayer()
        {
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer p = mPlayers[i];
                if (p.IsAlive && p.IsDummy)
                {
                    return p;
                }
            }
            return null;
        }

        public int GetDummyPlayerIndex()
        {
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer p = mPlayers[i];
                if (p.IsAlive && p.IsDummy)
                {
                    return i;
                }
            }
            return -1;
        }

        public int PlayerSpeaking
        {
            get { return mPlayerSpeaking; }
        }

        public int CurrentNominant
        {
            get { return mCurrentNominant; }
            set { mCurrentNominant = value; }
        }

        public int Round
        {
            get { return mRound; }
        }

        public int MafiaCount
        {
            get { return mMafiaCount; }
        }

        public int CivilCount
        {
            get { return mCivilCount; }
        }

        public int NumPlayers
        {
            get { return mCivilCount + mMafiaCount; }
        }

        public void Submit()
        {
            switch (mState)
            {
                case GameState.MafiaWon:
                case GameState.CivilWon:
                case GameState.Terminated:
                    break;
                default:
                    return;
            }

            try
            {
                string log = GenerateTextLog();
                Gate.Request request = new Gate.Request(Database.Gate, Gate.Request.Code.SubmitGame);
                request.Add(log);
                if (Database.Gate.Connected)
                {
                    request.Send();
                }
                else
                {
                    Database.AddDelayedRequest(request);
                }

                File.Delete(mDataPath);

                for (int i = 0; i < 10; ++i)
                {
                    mPlayers[i].Reset();
                }
                mMafiaCount = 3;
                mCivilCount = 7;

                mState = GameState.NotStarted;
                mRound = 0;
                mPlayerSpeaking = -1;
                mTableOpener = 0;
                mCurrentNominant = -1;
                mVotings = null;
                mCurrentVoting = null;
                mShooting = null;
                mLog = null;
                mLang = 0;
                ModeratorId = -1;
            }
            finally
            {
                mOnGameAction();
            }
        }

        public int ClubId
        {
            get { return mClubId; }
        }

        public int PartyId
        {
            get { return mPartyId; }
            set
            {
                if (mState != GameState.NotStarted)
                {
                    throw new GameException(Strings.ErrGameStarted);
                }

                if (value != mPartyId)
                {
                    mPartyId = value;

                    for (int i = 0; i < 10; ++i)
                    {
                        mPlayers[i].SetUser(null);
                    }
                    ModeratorId = -1;
                }
            }
        }

        public GameRules Rules
        {
            get { return mRules; }
            set
            {
                if (mState != GameState.NotStarted)
                {
                    throw new GameException(Strings.ErrGameStarted);
                }
                mRules = value;
            }
        }

        public int BestPlayer
        {
            get { return mBestPlayer; }
            set
            {
                if (
                    value < 0 ||
                    value >= 10 ||
                    (mState != GameState.CivilWon && mState != GameState.MafiaWon) ||
                    mPlayers[value].IsDummy)
                {
                    value = -1;
                }
                if (value != mBestPlayer)
                {
                    mBestPlayer = value;
                    Save(false);
                }
            }
        }

        public event GameActionListener OnGameAction
        {
            add
            {
                mOnGameAction += value;
            }
            remove
            {
                mOnGameAction -= value;
            }
        }

        public void UpdateData()
        {
            if (mState != GameState.NotStarted)
            {
                return;
            }

            Party party = Database.Parties[mPartyId];
            if (party == null)
            {
                for (int i = 0; i < 10; ++i)
                {
                    mPlayers[i].SetUser(null);
                }
            }
            else
            {
                for (int i = 0; i < 10; ++i)
                {
                    GamePlayer player = mPlayers[i];
                    if (player != null && !party.IsUserRegistered(player.UserId))
                    {
                        mPlayers[i].SetUser(null);
                    }
                }
            }
            ModeratorId = -1;
            mClubId = Database.Club.Id;
        }
    }
}
